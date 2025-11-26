<?php

namespace App\Service;

use App\Entity\Server;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SshService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Execute command on remote server via SSH
     */
    public function executeCommand(Server $server, string $command, int $timeout = 300): string
    {
        $host = $server->getIpAddress();
        $user = $server->getSshUser();
        $port = $server->getSshPort();
        $privateKey = $server->getSshPrivateKey();

        if (!$privateKey) {
            throw new \RuntimeException('SSH private key not configured for server: ' . $server->getName());
        }

        // Save private key to temporary file
        $keyFile = $this->saveTempPrivateKey($privateKey);

        try {
            // Build SSH command
            $sshCommand = sprintf(
                'ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p %d %s@%s %s',
                escapeshellarg($keyFile),
                $port,
                escapeshellarg($user),
                escapeshellarg($host),
                escapeshellarg($command)
            );

            $this->logger->debug('Executing SSH command', [
                'host' => $host,
                'user' => $user,
                'command' => $command,
            ]);

            // Execute command
            $process = Process::fromShellCommandline($sshCommand);
            $process->setTimeout($timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->error('SSH command failed', [
                    'host' => $host,
                    'command' => $command,
                    'exit_code' => $process->getExitCode(),
                    'error' => $process->getErrorOutput(),
                ]);

                throw new ProcessFailedException($process);
            }

            return $process->getOutput();
        } finally {
            // Clean up temporary key file
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
        }
    }

    /**
     * Check if SSH connection is working
     */
    public function testConnection(Server $server): bool
    {
        try {
            $output = $this->executeCommand($server, 'echo "Connection successful"', 10);
            return str_contains($output, 'Connection successful');
        } catch (\Exception $e) {
            $this->logger->error('SSH connection test failed', [
                'server' => $server->getName(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Execute multiple commands in a single SSH session
     */
    public function executeCommands(Server $server, array $commands, int $timeout = 300): array
    {
        $results = [];

        foreach ($commands as $command) {
            try {
                $results[] = [
                    'command' => $command,
                    'success' => true,
                    'output' => $this->executeCommand($server, $command, $timeout),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'command' => $command,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Copy file to remote server
     */
    public function copyFileToServer(Server $server, string $localPath, string $remotePath): bool
    {
        $host = $server->getIpAddress();
        $user = $server->getSshUser();
        $port = $server->getSshPort();
        $privateKey = $server->getSshPrivateKey();

        if (!$privateKey) {
            throw new \RuntimeException('SSH private key not configured for server: ' . $server->getName());
        }

        if (!file_exists($localPath)) {
            throw new \RuntimeException('Local file not found: ' . $localPath);
        }

        $keyFile = $this->saveTempPrivateKey($privateKey);

        try {
            $scpCommand = sprintf(
                'scp -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s %s@%s:%s',
                escapeshellarg($keyFile),
                $port,
                escapeshellarg($localPath),
                escapeshellarg($user),
                escapeshellarg($host),
                escapeshellarg($remotePath)
            );

            $process = Process::fromShellCommandline($scpCommand);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return true;
        } finally {
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
        }
    }

    /**
     * Copy file from remote server
     */
    public function copyFileFromServer(Server $server, string $remotePath, string $localPath): bool
    {
        $host = $server->getIpAddress();
        $user = $server->getSshUser();
        $port = $server->getSshPort();
        $privateKey = $server->getSshPrivateKey();

        if (!$privateKey) {
            throw new \RuntimeException('SSH private key not configured for server: ' . $server->getName());
        }

        $keyFile = $this->saveTempPrivateKey($privateKey);

        try {
            $scpCommand = sprintf(
                'scp -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -P %d %s@%s:%s %s',
                escapeshellarg($keyFile),
                $port,
                escapeshellarg($user),
                escapeshellarg($host),
                escapeshellarg($remotePath),
                escapeshellarg($localPath)
            );

            $process = Process::fromShellCommandline($scpCommand);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return true;
        } finally {
            if (file_exists($keyFile)) {
                unlink($keyFile);
            }
        }
    }

    /**
     * Save private key to temporary file
     */
    private function saveTempPrivateKey(string $privateKey): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
        file_put_contents($tempFile, $privateKey);
        chmod($tempFile, 0600);
        return $tempFile;
    }
}
