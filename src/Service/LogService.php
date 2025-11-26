<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Server;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class LogService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get container logs from the server
     */
    public function getContainerLogs(Project $project, int $lines = 100, bool $follow = false): array
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            $this->logger->warning('Log fetch: No active server', ['projectId' => $project->getId()]);
            return [
                'success' => false,
                'error' => 'No active server assigned to this project',
                'logs' => '',
            ];
        }

        $containerId = $project->getContainerId();
        if (!$containerId) {
            $this->logger->warning('Log fetch: No container ID', ['projectId' => $project->getId()]);
            return [
                'success' => false,
                'error' => 'No container deployed for this project',
                'logs' => '',
            ];
        }

        $containerName = 'pushify-' . $project->getSlug();

        try {
            $command = "docker logs {$containerName} --tail {$lines} 2>&1";
            $this->logger->info('Log fetch: Executing SSH command', [
                'container' => $containerName,
                'server' => $server->getIpAddress(),
            ]);

            $result = $this->executeRemoteCommand($server, $command);

            $this->logger->info('Log fetch: SSH result', [
                'success' => $result['success'],
                'outputLength' => strlen($result['output'] ?? ''),
                'error' => $result['error'] ?? null,
            ]);

            if ($result['success']) {
                return [
                    'success' => true,
                    'logs' => $result['output'],
                    'container' => $containerName,
                    'server' => $server->getIpAddress(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?: 'Failed to fetch logs',
                    'logs' => '',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to get container logs', [
                'project' => $project->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => '',
            ];
        }
    }

    /**
     * Get container status and stats
     */
    public function getContainerStatus(Project $project): array
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            return [
                'success' => false,
                'error' => 'No active server assigned',
                'status' => 'unknown',
            ];
        }

        $containerName = 'pushify-' . $project->getSlug();

        try {
            // Get container status
            $statusCmd = "docker inspect --format='{{.State.Status}}' {$containerName} 2>/dev/null || echo 'not_found'";
            $statusResult = $this->executeRemoteCommand($server, $statusCmd);

            $status = trim($statusResult['output']);

            if ($status === 'not_found') {
                return [
                    'success' => true,
                    'status' => 'not_deployed',
                    'running' => false,
                ];
            }

            // Get container stats if running
            $stats = null;
            if ($status === 'running') {
                $statsCmd = "docker stats {$containerName} --no-stream --format '{{json .}}' 2>/dev/null";
                $statsResult = $this->executeRemoteCommand($server, $statsCmd);

                if ($statsResult['success'] && $statsResult['output']) {
                    $stats = json_decode($statsResult['output'], true);
                }
            }

            // Get container info
            $infoCmd = "docker inspect --format='{{.State.StartedAt}}|{{.State.Health.Status}}|{{.RestartCount}}' {$containerName} 2>/dev/null";
            $infoResult = $this->executeRemoteCommand($server, $infoCmd);
            $infoParts = explode('|', trim($infoResult['output']));

            return [
                'success' => true,
                'status' => $status,
                'running' => $status === 'running',
                'startedAt' => $infoParts[0] ?? null,
                'healthStatus' => ($infoParts[1] ?? '') !== '' ? $infoParts[1] : null,
                'restartCount' => (int) ($infoParts[2] ?? 0),
                'stats' => $stats,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    /**
     * Restart the container
     */
    public function restartContainer(Project $project): array
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            return ['success' => false, 'error' => 'No active server'];
        }

        $containerName = 'pushify-' . $project->getSlug();

        $result = $this->executeRemoteCommand($server, "docker restart {$containerName}");

        return [
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?: 'Failed to restart container'),
        ];
    }

    /**
     * Stop the container
     */
    public function stopContainer(Project $project): array
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            return ['success' => false, 'error' => 'No active server'];
        }

        $containerName = 'pushify-' . $project->getSlug();

        $result = $this->executeRemoteCommand($server, "docker stop {$containerName}");

        return [
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?: 'Failed to stop container'),
        ];
    }

    /**
     * Start the container
     */
    public function startContainer(Project $project): array
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            return ['success' => false, 'error' => 'No active server'];
        }

        $containerName = 'pushify-' . $project->getSlug();

        $result = $this->executeRemoteCommand($server, "docker start {$containerName}");

        return [
            'success' => $result['success'],
            'error' => $result['success'] ? null : ($result['error'] ?: 'Failed to start container'),
        ];
    }

    /**
     * Stream container logs in real-time via SSE
     * @param callable $callback Function to call for each log line, return false to stop streaming
     */
    public function streamContainerLogs(Project $project, int $tailLines, callable $callback): void
    {
        $server = $project->getServer();

        if (!$server || !$server->isActive()) {
            $callback('[ERROR] No active server assigned');
            return;
        }

        $containerName = 'pushify-' . $project->getSlug();
        $keyFile = $this->createTempKeyFile($server);

        try {
            // Use docker logs with -f (follow) to stream logs
            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                '-o', 'BatchMode=yes',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                "docker logs {$containerName} --tail {$tailLines} -f 2>&1"
            ]);

            $process->setTimeout(300); // 5 minutes max
            $process->start();

            $buffer = '';

            // Read output incrementally
            while ($process->isRunning()) {
                $output = $process->getIncrementalOutput();
                $errorOutput = $process->getIncrementalErrorOutput();

                $combined = $output . $errorOutput;

                if ($combined) {
                    $buffer .= $combined;

                    // Process complete lines
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        if (trim($line) !== '') {
                            $continue = $callback($line);
                            if ($continue === false) {
                                $process->stop();
                                break 2;
                            }
                        }
                    }
                }

                // Small delay to prevent CPU spinning
                usleep(50000); // 50ms
            }

            // Process any remaining buffer
            if ($buffer && trim($buffer) !== '') {
                $callback($buffer);
            }

        } catch (\Exception $e) {
            $callback('[ERROR] Stream failed: ' . $e->getMessage());
        } finally {
            @unlink($keyFile);
        }
    }

    private function executeRemoteCommand(Server $server, string $command): array
    {
        try {
            $keyFile = $this->createTempKeyFile($server);

            $this->logger->debug('SSH: Creating connection', [
                'host' => $server->getIpAddress(),
                'port' => $server->getSshPort(),
                'user' => $server->getSshUser(),
            ]);

            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                '-o', 'BatchMode=yes',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                $command
            ]);
            $process->setTimeout(30);
            $process->run();

            @unlink($keyFile);

            $this->logger->debug('SSH: Command completed', [
                'exitCode' => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
            ]);

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('SSH: Exception during execution', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'output' => '',
                'error' => 'SSH execution failed: ' . $e->getMessage(),
            ];
        }
    }

    private function createTempKeyFile(Server $server): string
    {
        $privateKey = $server->getSshPrivateKey();
        if (!$privateKey) {
            throw new \RuntimeException('Server has no SSH private key configured');
        }

        $keyFile = sys_get_temp_dir() . '/pushify_logs_' . uniqid() . '.key';
        file_put_contents($keyFile, $privateKey);
        chmod($keyFile, 0600);
        return $keyFile;
    }
}
