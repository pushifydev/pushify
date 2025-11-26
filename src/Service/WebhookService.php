<?php

namespace App\Service;

use App\Entity\Deployment;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private WebhookRepository $webhookRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Trigger webhooks for a specific event
     */
    public function trigger(string $event, array $payload, ?Project $project = null): void
    {
        $webhooks = $this->webhookRepository->findForEvent($event, $project);

        foreach ($webhooks as $webhook) {
            $this->send($webhook, $event, $payload);
        }
    }

    /**
     * Trigger deployment webhooks
     */
    public function triggerDeployment(Deployment $deployment, string $event): void
    {
        $payload = $this->buildDeploymentPayload($deployment, $event);
        $this->trigger($event, $payload, $deployment->getProject());
    }

    /**
     * Trigger domain webhooks
     */
    public function triggerDomain(Domain $domain, string $event): void
    {
        $payload = $this->buildDomainPayload($domain, $event);
        $this->trigger($event, $payload, $domain->getProject());
    }

    /**
     * Send webhook request
     */
    public function send(Webhook $webhook, string $event, array $payload): bool
    {
        try {
            $body = $this->formatPayload($webhook, $event, $payload);
            $headers = $this->buildHeaders($webhook, $body);

            $response = $this->httpClient->request('POST', $webhook->getUrl(), [
                'headers' => $headers,
                'body' => $body,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            // Update webhook status
            $webhook->setLastTriggeredAt(new \DateTimeImmutable());
            $webhook->setLastResponseCode($statusCode);

            if ($statusCode >= 200 && $statusCode < 300) {
                $webhook->incrementSuccessCount();
                $webhook->setLastError(null);
                $this->entityManager->flush();
                return true;
            } else {
                $webhook->incrementFailureCount();
                $webhook->setLastError("HTTP {$statusCode}");
                $this->entityManager->flush();
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Webhook delivery failed', [
                'webhook_id' => $webhook->getId(),
                'url' => $webhook->getUrl(),
                'error' => $e->getMessage(),
            ]);

            $webhook->setLastTriggeredAt(new \DateTimeImmutable());
            $webhook->setLastResponseCode(0);
            $webhook->setLastError($e->getMessage());
            $webhook->incrementFailureCount();
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Test a webhook
     */
    public function test(Webhook $webhook): array
    {
        $payload = [
            'test' => true,
            'message' => 'This is a test webhook from Pushify',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];

        try {
            $body = $this->formatPayload($webhook, 'test', $payload);
            $headers = $this->buildHeaders($webhook, $body);

            $response = $this->httpClient->request('POST', $webhook->getUrl(), [
                'headers' => $headers,
                'body' => $body,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'statusCode' => $statusCode,
                'response' => $response->getContent(false),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'statusCode' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format payload based on webhook preset
     */
    private function formatPayload(Webhook $webhook, string $event, array $payload): string
    {
        return match ($webhook->getPreset()) {
            Webhook::PRESET_SLACK => $this->formatSlackPayload($event, $payload),
            Webhook::PRESET_DISCORD => $this->formatDiscordPayload($event, $payload),
            Webhook::PRESET_TEAMS => $this->formatTeamsPayload($event, $payload),
            default => json_encode([
                'event' => $event,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
                'data' => $payload,
            ]),
        };
    }

    /**
     * Format Slack payload
     */
    private function formatSlackPayload(string $event, array $payload): string
    {
        $color = $this->getEventColor($event);
        $emoji = $this->getEventEmoji($event);
        $title = $this->getEventTitle($event);

        $fields = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $fields[] = [
                    'title' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => (string) $value,
                    'short' => strlen((string) $value) < 30,
                ];
            }
        }

        return json_encode([
            'attachments' => [
                [
                    'color' => $color,
                    'title' => "{$emoji} {$title}",
                    'fields' => $fields,
                    'footer' => 'Pushify',
                    'ts' => time(),
                ],
            ],
        ]);
    }

    /**
     * Format Discord payload
     */
    private function formatDiscordPayload(string $event, array $payload): string
    {
        $color = hexdec(ltrim($this->getEventColor($event), '#'));
        $emoji = $this->getEventEmoji($event);
        $title = $this->getEventTitle($event);

        $fields = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $fields[] = [
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => (string) $value,
                    'inline' => strlen((string) $value) < 30,
                ];
            }
        }

        return json_encode([
            'embeds' => [
                [
                    'title' => "{$emoji} {$title}",
                    'color' => $color,
                    'fields' => $fields,
                    'footer' => ['text' => 'Pushify'],
                    'timestamp' => (new \DateTimeImmutable())->format('c'),
                ],
            ],
        ]);
    }

    /**
     * Format Microsoft Teams payload
     */
    private function formatTeamsPayload(string $event, array $payload): string
    {
        $color = $this->getEventColor($event);
        $emoji = $this->getEventEmoji($event);
        $title = $this->getEventTitle($event);

        $facts = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $facts[] = [
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'value' => (string) $value,
                ];
            }
        }

        return json_encode([
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => ltrim($color, '#'),
            'summary' => $title,
            'sections' => [
                [
                    'activityTitle' => "{$emoji} {$title}",
                    'facts' => $facts,
                ],
            ],
        ]);
    }

    /**
     * Build request headers
     */
    private function buildHeaders(Webhook $webhook, string $body): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Pushify-Webhook/1.0',
        ];

        // Add custom headers
        if ($webhook->getHeaders()) {
            $headers = array_merge($headers, $webhook->getHeaders());
        }

        // Add signature if secret is set
        if ($webhook->getSecret()) {
            $signature = hash_hmac('sha256', $body, $webhook->getSecret());
            $headers['X-Pushify-Signature'] = $signature;
        }

        return $headers;
    }

    /**
     * Build deployment payload
     */
    private function buildDeploymentPayload(Deployment $deployment, string $event): array
    {
        $project = $deployment->getProject();
        $triggeredBy = $deployment->getTriggeredBy();

        $payload = [
            'deployment_id' => $deployment->getId(),
            'project' => $project->getName(),
            'project_url' => $project->getProductionUrl(),
            'branch' => $deployment->getBranch(),
            'commit' => $deployment->getShortCommitHash(),
            'commit_message' => $deployment->getCommitMessage(),
            'status' => $deployment->getStatus(),
            'trigger' => $deployment->getTrigger(),
            'triggered_by' => $triggeredBy?->getEmail() ?? 'system',
            'duration' => $deployment->getTotalDuration(),
        ];

        // Add rollback-specific info
        if ($deployment->isRollback()) {
            $rollbackFrom = $deployment->getRollbackFrom();
            $payload['is_rollback'] = true;
            $payload['rollback_from_deployment'] = $rollbackFrom?->getId();
        }

        return $payload;
    }

    /**
     * Build domain payload
     */
    private function buildDomainPayload(Domain $domain, string $event): array
    {
        return [
            'event' => $event,
            'domain' => $domain->getDomain(),
            'project' => $domain->getProject()?->getName(),
            'status' => $domain->getStatus(),
            'ssl_enabled' => $domain->isSslEnabled(),
        ];
    }

    /**
     * Get event color
     */
    private function getEventColor(string $event): string
    {
        return match ($event) {
            Webhook::EVENT_DEPLOYMENT_SUCCESS, Webhook::EVENT_DOMAIN_VERIFIED, Webhook::EVENT_SSL_ISSUED => '#22c55e',
            Webhook::EVENT_DEPLOYMENT_FAILED => '#ef4444',
            Webhook::EVENT_DEPLOYMENT_STARTED => '#3b82f6',
            Webhook::EVENT_DEPLOYMENT_CANCELLED => '#6b7280',
            Webhook::EVENT_SSL_EXPIRING => '#f59e0b',
            default => '#8b5cf6',
        };
    }

    /**
     * Get event emoji
     */
    private function getEventEmoji(string $event): string
    {
        return match ($event) {
            Webhook::EVENT_DEPLOYMENT_SUCCESS => '✅',
            Webhook::EVENT_DEPLOYMENT_FAILED => '❌',
            Webhook::EVENT_DEPLOYMENT_STARTED => '🚀',
            Webhook::EVENT_DEPLOYMENT_CANCELLED => '⏹️',
            Webhook::EVENT_DOMAIN_ADDED => '🌐',
            Webhook::EVENT_DOMAIN_VERIFIED => '✓',
            Webhook::EVENT_SSL_ISSUED => '🔒',
            Webhook::EVENT_SSL_EXPIRING => '⚠️',
            'test' => '🧪',
            default => '📣',
        };
    }

    /**
     * Get event title
     */
    private function getEventTitle(string $event): string
    {
        return match ($event) {
            Webhook::EVENT_DEPLOYMENT_SUCCESS => 'Deployment Succeeded',
            Webhook::EVENT_DEPLOYMENT_FAILED => 'Deployment Failed',
            Webhook::EVENT_DEPLOYMENT_STARTED => 'Deployment Started',
            Webhook::EVENT_DEPLOYMENT_CANCELLED => 'Deployment Cancelled',
            Webhook::EVENT_DOMAIN_ADDED => 'Domain Added',
            Webhook::EVENT_DOMAIN_VERIFIED => 'Domain Verified',
            Webhook::EVENT_SSL_ISSUED => 'SSL Certificate Issued',
            Webhook::EVENT_SSL_EXPIRING => 'SSL Certificate Expiring',
            'test' => 'Test Webhook',
            default => ucfirst(str_replace('.', ' ', $event)),
        };
    }
}
