<?php

namespace App\Service;

use App\Entity\HealthCheck;
use App\Entity\Project;
use App\Repository\HealthCheckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonitoringService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HealthCheckRepository $healthCheckRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Perform a health check on a project
     */
    public function performHealthCheck(Project $project): HealthCheck
    {
        $healthCheck = new HealthCheck();
        $healthCheck->setProject($project);

        try {
            // Check if container is running
            $isRunning = $this->isContainerRunning($project);
            $healthCheck->setIsContainerRunning($isRunning);

            if ($isRunning) {
                // Get container stats
                $stats = $this->getContainerStats($project);

                if ($stats['success']) {
                    $healthCheck->setCpuUsage($stats['cpu'] ?? null);
                    $healthCheck->setMemoryUsage($stats['memory_percent'] ?? null);
                    $healthCheck->setMemoryUsageBytes($stats['memory_usage'] ?? null);
                    $healthCheck->setMemoryLimitBytes($stats['memory_limit'] ?? null);
                }

                // Check HTTP endpoint
                if ($project->getProductionUrl()) {
                    $responseCheck = $this->checkHttpEndpoint($project->getProductionUrl());
                    $healthCheck->setResponseTime($responseCheck['response_time'] ?? null);
                    $healthCheck->setHttpStatusCode($responseCheck['status_code'] ?? null);

                    if (!$responseCheck['success']) {
                        $healthCheck->setErrorMessage($responseCheck['error'] ?? 'HTTP check failed');
                    }
                }

                // Determine overall status
                $status = $this->determineStatus($healthCheck);
                $healthCheck->setStatus($status);
            } else {
                $healthCheck->setStatus(HealthCheck::STATUS_DOWN);
                $healthCheck->setErrorMessage('Container is not running');
            }
        } catch (\Exception $e) {
            $healthCheck->setStatus(HealthCheck::STATUS_DOWN);
            $healthCheck->setErrorMessage($e->getMessage());
            $this->logger->error('Health check failed', [
                'project' => $project->getId(),
                'error' => $e->getMessage()
            ]);
        }

        $this->entityManager->persist($healthCheck);
        $this->entityManager->flush();

        return $healthCheck;
    }

    /**
     * Check if container is running
     */
    private function isContainerRunning(Project $project): bool
    {
        $containerName = 'pushify-' . $project->getSlug();
        $process = new Process(['docker', 'ps', '--filter', "name={$containerName}", '--format', '{{.Names}}']);
        $process->run();

        return trim($process->getOutput()) === $containerName;
    }

    /**
     * Get container resource stats
     */
    private function getContainerStats(Project $project): array
    {
        $containerName = 'pushify-' . $project->getSlug();

        $process = new Process([
            'docker', 'stats', $containerName,
            '--no-stream', '--format',
            '{{.CPUPerc}}|{{.MemPerc}}|{{.MemUsage}}'
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            return ['success' => false];
        }

        $output = trim($process->getOutput());
        if (empty($output)) {
            return ['success' => false];
        }

        $parts = explode('|', $output);

        // Parse CPU (e.g., "1.23%")
        $cpu = isset($parts[0]) ? (float) str_replace('%', '', $parts[0]) : null;

        // Parse Memory percent (e.g., "5.67%")
        $memoryPercent = isset($parts[1]) ? (float) str_replace('%', '', $parts[1]) : null;

        // Parse Memory usage (e.g., "123.4MiB / 2GiB")
        $memoryUsage = null;
        $memoryLimit = null;

        if (isset($parts[2])) {
            $memParts = explode(' / ', $parts[2]);
            if (count($memParts) === 2) {
                $memoryUsage = $this->parseMemorySize($memParts[0]);
                $memoryLimit = $this->parseMemorySize($memParts[1]);
            }
        }

        return [
            'success' => true,
            'cpu' => $cpu,
            'memory_percent' => $memoryPercent,
            'memory_usage' => $memoryUsage,
            'memory_limit' => $memoryLimit,
        ];
    }

    /**
     * Parse memory size string to bytes
     */
    private function parseMemorySize(string $size): ?int
    {
        $size = trim($size);
        if (preg_match('/^([\d.]+)\s*([KMGT]i?B)$/i', $size, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            $multipliers = [
                'B' => 1,
                'KB' => 1024,
                'KIB' => 1024,
                'MB' => 1024 * 1024,
                'MIB' => 1024 * 1024,
                'GB' => 1024 * 1024 * 1024,
                'GIB' => 1024 * 1024 * 1024,
                'TB' => 1024 * 1024 * 1024 * 1024,
                'TIB' => 1024 * 1024 * 1024 * 1024,
            ];

            return (int) ($value * ($multipliers[$unit] ?? 1));
        }

        return null;
    }

    /**
     * Check HTTP endpoint
     */
    private function checkHttpEndpoint(string $url): array
    {
        try {
            $startTime = microtime(true);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_redirects' => 5,
            ]);

            $statusCode = $response->getStatusCode();
            $endTime = microtime(true);
            $responseTime = (int) (($endTime - $startTime) * 1000); // Convert to milliseconds

            return [
                'success' => $statusCode >= 200 && $statusCode < 400,
                'status_code' => $statusCode,
                'response_time' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine overall health status
     */
    private function determineStatus(HealthCheck $healthCheck): string
    {
        // Down if container not running or HTTP failed badly
        if (!$healthCheck->isContainerRunning()) {
            return HealthCheck::STATUS_DOWN;
        }

        if ($healthCheck->getHttpStatusCode() && $healthCheck->getHttpStatusCode() >= 500) {
            return HealthCheck::STATUS_DOWN;
        }

        // Degraded if any metric is concerning
        $isDegraded = false;

        if ($healthCheck->getCpuUsage() && $healthCheck->getCpuUsage() > 80) {
            $isDegraded = true;
        }

        if ($healthCheck->getMemoryUsage() && $healthCheck->getMemoryUsage() > 85) {
            $isDegraded = true;
        }

        if ($healthCheck->getResponseTime() && $healthCheck->getResponseTime() > 3000) {
            $isDegraded = true;
        }

        return $isDegraded ? HealthCheck::STATUS_DEGRADED : HealthCheck::STATUS_HEALTHY;
    }

    /**
     * Get uptime statistics for a project
     */
    public function getUptimeStats(Project $project, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $uptime = $this->healthCheckRepository->calculateUptime($project, $since);
        $avgResponseTime = $this->healthCheckRepository->getAverageResponseTime($project, $since);
        $latestCheck = $this->healthCheckRepository->findLatestForProject($project);

        return [
            'uptime_percent' => $uptime,
            'avg_response_time' => $avgResponseTime,
            'latest_check' => $latestCheck,
            'period_days' => $days,
        ];
    }
}
