<?php

namespace App\Service;

use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class ServerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $projectDir
    ) {
    }

    /**
     * Test SSH connection to a server
     */
    public function testConnection(Server $server): array
    {
        $server->setStatus(Server::STATUS_CONNECTING);
        $this->entityManager->flush();

        try {
            // Create temporary key file
            $keyFile = $this->createTempKeyFile($server);

            // Test SSH connection
            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'ConnectTimeout=10',
                '-o', 'BatchMode=yes',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                'echo "CONNECTION_OK" && uname -a'
            ]);
            $process->setTimeout(30);
            $process->run();

            // Clean up temp key
            @unlink($keyFile);

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput() ?: 'Connection failed');
            }

            $output = $process->getOutput();
            if (!str_contains($output, 'CONNECTION_OK')) {
                throw new \RuntimeException('Unexpected response from server');
            }

            // Parse OS info
            $osInfo = trim(str_replace('CONNECTION_OK', '', $output));

            $server->setStatus(Server::STATUS_ACTIVE);
            $server->setLastConnectedAt(new \DateTimeImmutable());
            $server->setLastError(null);

            // Try to detect OS
            if (str_contains($osInfo, 'Ubuntu')) {
                $server->setOs('Ubuntu');
            } elseif (str_contains($osInfo, 'Debian')) {
                $server->setOs('Debian');
            } elseif (str_contains($osInfo, 'CentOS')) {
                $server->setOs('CentOS');
            }

            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'os_info' => $osInfo,
            ];
        } catch (\Exception $e) {
            $server->setStatus(Server::STATUS_ERROR);
            $server->setLastError($e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('SSH connection failed', [
                'server' => $server->getName(),
                'ip' => $server->getIpAddress(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if Docker is installed and get version
     */
    public function checkDocker(Server $server): array
    {
        try {
            $keyFile = $this->createTempKeyFile($server);

            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'ConnectTimeout=10',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                'docker --version 2>/dev/null || echo "NOT_INSTALLED"'
            ]);
            $process->setTimeout(30);
            $process->run();

            @unlink($keyFile);

            $output = trim($process->getOutput());

            if (str_contains($output, 'NOT_INSTALLED') || !$process->isSuccessful()) {
                $server->setDockerInstalled(false);
                $server->setDockerVersion(null);
                $this->entityManager->flush();

                return [
                    'installed' => false,
                    'message' => 'Docker is not installed',
                ];
            }

            // Parse version: "Docker version 24.0.5, build 24.0.5-0ubuntu1"
            preg_match('/Docker version ([0-9.]+)/', $output, $matches);
            $version = $matches[1] ?? 'unknown';

            $server->setDockerInstalled(true);
            $server->setDockerVersion($version);
            $this->entityManager->flush();

            return [
                'installed' => true,
                'version' => $version,
            ];
        } catch (\Exception $e) {
            return [
                'installed' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Install Docker on the server
     */
    public function installDocker(Server $server): array
    {
        try {
            $keyFile = $this->createTempKeyFile($server);

            // Docker install script for Ubuntu/Debian
            $installScript = <<<'BASH'
curl -fsSL https://get.docker.com -o get-docker.sh &&
sh get-docker.sh &&
systemctl enable docker &&
systemctl start docker &&
docker --version
BASH;

            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                $installScript
            ]);
            $process->setTimeout(300); // 5 minutes for installation
            $process->run();

            @unlink($keyFile);

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput() ?: 'Installation failed');
            }

            // Re-check Docker
            return $this->checkDocker($server);
        } catch (\Exception $e) {
            return [
                'installed' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get server system info
     */
    public function getSystemInfo(Server $server): array
    {
        try {
            $keyFile = $this->createTempKeyFile($server);

            $commands = [
                'cpu' => "nproc",
                'memory' => "free -m | awk '/^Mem:/{print \$2}'",
                'disk' => "df -BG / | awk 'NR==2{print \$2}' | tr -d 'G'",
            ];

            $info = [];
            foreach ($commands as $key => $cmd) {
                $process = new Process([
                    'ssh',
                    '-i', $keyFile,
                    '-o', 'StrictHostKeyChecking=no',
                    '-o', 'ConnectTimeout=10',
                    '-p', (string) $server->getSshPort(),
                    $server->getSshUser() . '@' . $server->getIpAddress(),
                    $cmd
                ]);
                $process->setTimeout(10);
                $process->run();
                $info[$key] = trim($process->getOutput());
            }

            @unlink($keyFile);

            // Update server info
            if (is_numeric($info['cpu'])) {
                $server->setCpuCores((int) $info['cpu']);
            }
            if (is_numeric($info['memory'])) {
                $server->setMemoryMb((int) $info['memory']);
            }
            if (is_numeric($info['disk'])) {
                $server->setDiskGb((int) $info['disk']);
            }

            $this->entityManager->flush();

            return [
                'success' => true,
                'cpu_cores' => $server->getCpuCores(),
                'memory_mb' => $server->getMemoryMb(),
                'disk_gb' => $server->getDiskGb(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a command on the server
     */
    public function executeCommand(Server $server, string $command, int $timeout = 60): array
    {
        try {
            $keyFile = $this->createTempKeyFile($server);

            $process = new Process([
                'ssh',
                '-i', $keyFile,
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'ConnectTimeout=10',
                '-p', (string) $server->getSshPort(),
                $server->getSshUser() . '@' . $server->getIpAddress(),
                $command
            ]);
            $process->setTimeout($timeout);
            $process->run();

            @unlink($keyFile);

            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Create a temporary SSH key file
     */
    private function createTempKeyFile(Server $server): string
    {
        $keyFile = sys_get_temp_dir() . '/pushify_ssh_' . uniqid() . '.key';
        file_put_contents($keyFile, $server->getSshPrivateKey());
        chmod($keyFile, 0600);
        return $keyFile;
    }

    /**
     * Generate SSH key pair for a new server
     */
    public function generateKeyPair(): array
    {
        $keyDir = sys_get_temp_dir() . '/pushify_keygen_' . uniqid();
        mkdir($keyDir, 0700);

        $process = new Process([
            'ssh-keygen',
            '-t', 'ed25519',
            '-f', $keyDir . '/key',
            '-N', '', // No passphrase
            '-C', 'pushify-deploy'
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to generate SSH key');
        }

        $privateKey = file_get_contents($keyDir . '/key');
        $publicKey = file_get_contents($keyDir . '/key.pub');

        // Cleanup
        @unlink($keyDir . '/key');
        @unlink($keyDir . '/key.pub');
        @rmdir($keyDir);

        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }
}
