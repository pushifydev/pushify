<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\HealthCheck;
use App\Entity\Project;
use App\Repository\AlertRepository;
use App\Repository\AlertRuleRepository;
use App\Repository\HealthCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AlertService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlertRepository $alertRepository,
        private AlertRuleRepository $alertRuleRepository,
        private HealthCheckRepository $healthCheckRepository,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Check alert rules for a project and trigger alerts if needed
     */
    public function checkAlertRules(Project $project, HealthCheck $healthCheck): void
    {
        $rules = $this->alertRuleRepository->findEnabledForProject($project);

        foreach ($rules as $rule) {
            if ($this->shouldTriggerAlert($rule, $healthCheck)) {
                $this->triggerAlert($rule, $healthCheck);
            }
        }
    }

    /**
     * Check if an alert should be triggered based on rule
     */
    private function shouldTriggerAlert(AlertRule $rule, HealthCheck $healthCheck): bool
    {
        $value = $this->getMetricValue($rule->getMetric(), $healthCheck);

        if ($value === null) {
            return false;
        }

        // Check if condition is met
        $conditionMet = match ($rule->getOperator()) {
            '>' => $value > $rule->getThreshold(),
            '<' => $value < $rule->getThreshold(),
            '=' => abs($value - $rule->getThreshold()) < 0.01,
            default => false,
        };

        if (!$conditionMet) {
            return false;
        }

        // Check duration - condition must persist for X minutes
        if ($rule->getDuration() > 1) {
            $since = new \DateTimeImmutable("-{$rule->getDuration()} minutes");
            $recentChecks = $this->healthCheckRepository->findByProjectAndTimeRange(
                $healthCheck->getProject(),
                $since,
                new \DateTimeImmutable()
            );

            // All recent checks must meet the condition
            foreach ($recentChecks as $check) {
                $checkValue = $this->getMetricValue($rule->getMetric(), $check);
                if ($checkValue === null) {
                    return false;
                }

                $checkConditionMet = match ($rule->getOperator()) {
                    '>' => $checkValue > $rule->getThreshold(),
                    '<' => $checkValue < $rule->getThreshold(),
                    '=' => abs($checkValue - $rule->getThreshold()) < 0.01,
                    default => false,
                };

                if (!$checkConditionMet) {
                    return false;
                }
            }
        }

        // Check if we already have an unresolved alert for this rule recently
        $recentAlerts = $this->alertRepository->findUnresolvedForProject($healthCheck->getProject());
        foreach ($recentAlerts as $alert) {
            if ($alert->getRule()?->getId() === $rule->getId()) {
                // Don't create duplicate alerts
                return false;
            }
        }

        return true;
    }

    /**
     * Get metric value from health check
     */
    private function getMetricValue(string $metric, HealthCheck $healthCheck): ?float
    {
        return match ($metric) {
            AlertRule::METRIC_CPU => $healthCheck->getCpuUsage(),
            AlertRule::METRIC_MEMORY => $healthCheck->getMemoryUsage(),
            AlertRule::METRIC_DISK => $healthCheck->getDiskUsage(),
            AlertRule::METRIC_RESPONSE_TIME => (float) $healthCheck->getResponseTime(),
            default => null,
        };
    }

    /**
     * Trigger an alert
     */
    private function triggerAlert(AlertRule $rule, HealthCheck $healthCheck): void
    {
        $value = $this->getMetricValue($rule->getMetric(), $healthCheck);

        $alert = new Alert();
        $alert->setProject($healthCheck->getProject());
        $alert->setRule($rule);
        $alert->setSeverity($this->determineSeverity($rule, $value));
        $alert->setTitle($rule->getName());
        $alert->setMessage($this->generateAlertMessage($rule, $value));
        $alert->setMetadata([
            'metric' => $rule->getMetric(),
            'threshold' => $rule->getThreshold(),
            'actual_value' => $value,
            'health_check_id' => $healthCheck->getId(),
        ]);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        // Update rule last triggered time
        $rule->setLastTriggeredAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->warning('Alert triggered', [
            'project' => $healthCheck->getProject()->getId(),
            'rule' => $rule->getName(),
            'value' => $value,
            'threshold' => $rule->getThreshold(),
        ]);

        // Send notification
        $this->sendAlertNotification($alert, $rule);
    }

    /**
     * Determine alert severity
     */
    private function determineSeverity(AlertRule $rule, ?float $value): string
    {
        if ($value === null) {
            return Alert::SEVERITY_WARNING;
        }

        // Critical if value is significantly over threshold
        $threshold = $rule->getThreshold();
        $diff = abs($value - $threshold);
        $percentDiff = ($diff / $threshold) * 100;

        if ($percentDiff > 50) {
            return Alert::SEVERITY_CRITICAL;
        }

        if ($percentDiff > 20) {
            return Alert::SEVERITY_WARNING;
        }

        return Alert::SEVERITY_INFO;
    }

    /**
     * Generate alert message
     */
    private function generateAlertMessage(AlertRule $rule, ?float $value): string
    {
        $metricLabel = match ($rule->getMetric()) {
            AlertRule::METRIC_CPU => 'CPU Usage',
            AlertRule::METRIC_MEMORY => 'Memory Usage',
            AlertRule::METRIC_DISK => 'Disk Usage',
            AlertRule::METRIC_RESPONSE_TIME => 'Response Time',
            AlertRule::METRIC_UPTIME => 'Uptime',
            default => $rule->getMetric(),
        };

        $unit = match ($rule->getMetric()) {
            AlertRule::METRIC_RESPONSE_TIME => 'ms',
            default => '%',
        };

        return sprintf(
            '%s is %s%.2f%s (threshold: %.2f%s)',
            $metricLabel,
            $rule->getOperator(),
            $value ?? 0,
            $unit,
            $rule->getThreshold(),
            $unit
        );
    }

    /**
     * Send alert notification
     */
    private function sendAlertNotification(Alert $alert, AlertRule $rule): void
    {
        try {
            if ($rule->getChannel() === AlertRule::CHANNEL_EMAIL) {
                $this->sendEmailNotification($alert);
            }

            $alert->setNotificationSent(true);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to send alert notification', [
                'alert_id' => $alert->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Alert $alert): void
    {
        $project = $alert->getProject();
        $owner = $project->getOwner();

        $subject = "[Pushify Alert] {$alert->getTitle()} - {$project->getName()}";
        $body = "
            <h2>Alert: {$alert->getTitle()}</h2>
            <p><strong>Project:</strong> {$project->getName()}</p>
            <p><strong>Severity:</strong> {$alert->getSeverity()}</p>
            <p><strong>Message:</strong> {$alert->getMessage()}</p>
            <p><strong>Time:</strong> {$alert->getCreatedAt()->format('Y-m-d H:i:s')}</p>
            <p><a href=\"{$this->getProjectUrl($project)}\">View Project</a></p>
        ";

        $this->emailService->sendEmail(
            $owner->getEmail(),
            $subject,
            $body
        );
    }

    /**
     * Get project URL (helper)
     */
    private function getProjectUrl(Project $project): string
    {
        $baseUrl = $_ENV['DEFAULT_URI'] ?? 'http://localhost:8000';
        return "{$baseUrl}/dashboard/projects/{$project->getSlug()}";
    }

    /**
     * Auto-resolve alerts if conditions are no longer met
     */
    public function autoResolveAlerts(Project $project, HealthCheck $healthCheck): void
    {
        $unresolvedAlerts = $this->alertRepository->findUnresolvedForProject($project);

        foreach ($unresolvedAlerts as $alert) {
            if (!$alert->getRule()) {
                continue;
            }

            // Check if condition is still met
            $value = $this->getMetricValue($alert->getRule()->getMetric(), $healthCheck);
            if ($value === null) {
                continue;
            }

            $conditionMet = match ($alert->getRule()->getOperator()) {
                '>' => $value > $alert->getRule()->getThreshold(),
                '<' => $value < $alert->getRule()->getThreshold(),
                '=' => abs($value - $alert->getRule()->getThreshold()) < 0.01,
                default => false,
            };

            // If condition is no longer met, resolve the alert
            if (!$conditionMet) {
                $alert->setResolved(true);
                $this->entityManager->flush();

                $this->logger->info('Alert auto-resolved', [
                    'alert_id' => $alert->getId(),
                    'project' => $project->getId(),
                ]);
            }
        }
    }
}
