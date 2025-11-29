<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Server;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

class ContainerLogsService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get container logs (streaming or tail)
     *
     * @param Project $project
     * @param int $tail Number of lines to fetch (default: 100, 0 = all)
     * @param bool $follow Whether to follow/stream logs
     * @return array{success: bool, logs: string, error?: string}
     */
    public function getLogs(Project $project, int $tail = 100, bool $follow = false): array
    {
        $containerId = $project->getContainerId();
        $containerName = 'pushify-' . $project->getSlug();
        $server = $project->getServer();

        $this->logger->info('Getting container logs', [
            'project' => $project->getSlug(),
            'container_name' => $containerName,
            'has_server' => $server !== null,
            'server_active' => $server ? $server->isActive() : false,
            'server_ip' => $server ? $server->getIpAddress() : null,
        ]);

        if ($server && $server->isActive()) {
            return $this->getRemoteLogs($server, $containerName, $tail, $follow);
        } else {
            return $this->getLocalLogs($containerName, $tail, $follow);
        }
    }

    /**
     * Stream logs from local Docker container
     */
    public function streamLocalLogs(string $containerName, int $tail = 100, callable $callback): void
    {
        $command = ['docker', 'logs', '--tail', (string)$tail, '--follow', '--timestamps', $containerName];

        $process = new Process($command);
        $process->setTimeout(null); // No timeout for streaming

        try {
            $process->start();

            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    $callback($data);
                } else {
                    // stderr
                    $callback($data);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to stream local logs', [
                'container' => $containerName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get logs from local Docker container
     */
    private function getLocalLogs(string $containerName, int $tail = 100, bool $follow = false): array
    {
        $command = ['docker', 'logs', '--tail', (string)$tail, '--timestamps', $containerName];

        if ($follow) {
            $command[] = '--follow';
        }

        $process = new Process($command);
        $process->setTimeout($follow ? null : 30);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();

            // Check if container doesn't exist
            if (str_contains($error, 'No such container')) {
                return [
                    'success' => false,
                    'logs' => '',
                    'error' => 'Container not found. Please deploy your project first.'
                ];
            }

            return [
                'success' => false,
                'logs' => '',
                'error' => $error ?: 'Failed to fetch container logs'
            ];
        }

        return [
            'success' => true,
            'logs' => $process->getOutput()
        ];
    }

    /**
     * Get logs from remote Docker container via SSH
     */
    private function getRemoteLogs(Server $server, string $containerName, int $tail = 100, bool $follow = false): array
    {
        $keyFile = $this->createTempKeyFile($server);

        $this->logger->info('Created temp SSH key file', [
            'key_file' => $keyFile,
            'key_exists' => file_exists($keyFile),
            'key_size' => file_exists($keyFile) ? filesize($keyFile) : 0,
            'key_perms' => file_exists($keyFile) ? substr(sprintf('%o', fileperms($keyFile)), -4) : null,
        ]);

        try {
            $dockerCommand = "docker logs --tail {$tail} --timestamps {$containerName}";

            if ($follow) {
                $dockerCommand .= " --follow";
            }

            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                $dockerCommand
            ]);

            $process->setTimeout($follow ? null : 30);
            $process->run();

            @unlink($keyFile);

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput();

                $this->logger->error('SSH command failed for remote logs', [
                    'server' => $server->getIpAddress(),
                    'container' => $containerName,
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $error,
                    'output' => $process->getOutput()
                ]);

                if (str_contains($error, 'No such container')) {
                    return [
                        'success' => false,
                        'logs' => '',
                        'error' => 'Container not found on server. Please deploy your project first.'
                    ];
                }

                return [
                    'success' => false,
                    'logs' => '',
                    'error' => $error ?: 'Failed to fetch container logs from server'
                ];
            }

            return [
                'success' => true,
                'logs' => $process->getOutput()
            ];

        } catch (\Exception $e) {
            @unlink($keyFile);

            $this->logger->error('Failed to get remote logs', [
                'server' => $server->getName(),
                'container' => $containerName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'logs' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if container is running
     */
    public function isContainerRunning(Project $project): bool
    {
        $containerName = 'pushify-' . $project->getSlug();
        $server = $project->getServer();

        if ($server && $server->isActive()) {
            return $this->isRemoteContainerRunning($server, $containerName);
        } else {
            return $this->isLocalContainerRunning($containerName);
        }
    }

    private function isLocalContainerRunning(string $containerName): bool
    {
        $process = new Process(['docker', 'inspect', '-f', '{{.State.Running}}', $containerName]);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) === 'true';
    }

    private function isRemoteContainerRunning(Server $server, string $containerName): bool
    {
        $keyFile = $this->createTempKeyFile($server);

        try {
            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                "docker inspect -f '{{.State.Running}}' {$containerName}"
            ]);

            $process->setTimeout(10);
            $process->run();

            @unlink($keyFile);

            return $process->isSuccessful() && trim($process->getOutput()) === 'true';

        } catch (\Exception $e) {
            @unlink($keyFile);
            return false;
        }
    }

    /**
     * Get container stats (CPU, Memory, etc.)
     */
    public function getContainerStats(Project $project): array
    {
        $containerName = 'pushify-' . $project->getSlug();
        $server = $project->getServer();

        if ($server && $server->isActive()) {
            return $this->getRemoteContainerStats($server, $containerName);
        } else {
            return $this->getLocalContainerStats($containerName);
        }
    }

    private function getLocalContainerStats(string $containerName): array
    {
        $process = new Process([
            'docker', 'stats', '--no-stream', '--format',
            '{"cpu":"{{.CPUPerc}}","memory":"{{.MemUsage}}","memoryPercent":"{{.MemPerc}}"}',
            $containerName
        ]);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['success' => false, 'error' => 'Failed to get container stats'];
        }

        try {
            $stats = json_decode(trim($process->getOutput()), true);
            return ['success' => true, 'stats' => $stats];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to parse stats'];
        }
    }

    private function getRemoteContainerStats(Server $server, string $containerName): array
    {
        $keyFile = $this->createTempKeyFile($server);

        try {
            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-o', 'ConnectTimeout=10',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                "docker stats --no-stream --format '{\"cpu\":\"{{.CPUPerc}}\",\"memory\":\"{{.MemUsage}}\",\"memoryPercent\":\"{{.MemPerc}}\"}' {$containerName}"
            ]);

            $process->setTimeout(10);
            $process->run();

            @unlink($keyFile);

            if (!$process->isSuccessful()) {
                return ['success' => false, 'error' => 'Failed to get container stats from server'];
            }

            try {
                $stats = json_decode(trim($process->getOutput()), true);
                return ['success' => true, 'stats' => $stats];
            } catch (\Exception $e) {
                return ['success' => false, 'error' => 'Failed to parse stats'];
            }

        } catch (\Exception $e) {
            @unlink($keyFile);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function createTempKeyFile(Server $server): string
    {
        $keyFile = sys_get_temp_dir() . '/ssh_key_' . uniqid();
        file_put_contents($keyFile, $server->getSshPrivateKey());
        chmod($keyFile, 0600);
        return $keyFile;
    }
}
