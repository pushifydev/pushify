<?php

namespace App\Service;

use App\Entity\Database;
use App\Entity\Project;
use App\Entity\Server;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SshService $sshService,
        private ValidationService $validationService
    ) {
    }

    /**
     * Create a new database container
     */
    public function createDatabase(Database $database): bool
    {
        try {
            // Validate all inputs before creating
            $this->validationService->validateDatabaseName($database->getName());
            $this->validationService->validateDatabaseVersion($database->getType(), $database->getVersion() ?? 'latest');

            if ($database->getUsername()) {
                $this->validationService->validateDatabaseUsername($database->getUsername());
            }

            if ($database->getMemorySizeMb()) {
                $this->validationService->validateMemorySize($database->getMemorySizeMb());
            }

            if ($database->getCpuLimit()) {
                $this->validationService->validateCpuLimit($database->getCpuLimit());
            }

            $database->setStatus(Database::STATUS_CREATING);
            $this->entityManager->flush();

            // Ensure pushify-network exists
            $this->ensureNetworkExists($database->getServer());

            // Generate unique container name
            $containerName = $this->generateContainerName($database);
            $database->setContainerName($containerName);

            // Assign a random available port
            $port = $this->findAvailablePort($database->getServer());
            $database->setPort($port);

            // Generate connection string
            $connectionString = $database->generateConnectionString();
            $database->setConnectionString($connectionString);

            // Create Docker container
            $containerId = $this->createDockerContainer($database);
            $database->setContainerId($containerId);

            // Start the container
            $this->startContainer($database);

            // Open firewall port if on remote server
            if ($database->getServer()) {
                $this->openFirewallPort($database->getServer(), $database->getPort());
            }

            // Wait for database to be ready
            sleep(3);

            // Initialize database (create database/user if needed)
            $this->initializeDatabase($database);

            $database->setStatus(Database::STATUS_RUNNING);
            $database->setStartedAt(new \DateTime());
            $database->setErrorMessage(null);
            $this->entityManager->flush();

            $this->logger->info('Database created successfully', [
                'database_id' => $database->getId(),
                'type' => $database->getType(),
                'name' => $database->getName(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create database', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            $database->setStatus(Database::STATUS_ERROR);
            $database->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Start a stopped database container
     */
    public function startDatabase(Database $database): bool
    {
        try {
            if (!$database->getContainerId()) {
                throw new \RuntimeException('Container ID not found');
            }

            $this->startContainer($database);

            $database->setStatus(Database::STATUS_RUNNING);
            $database->setStartedAt(new \DateTime());
            $database->setStoppedAt(null);
            $database->setErrorMessage(null);
            $this->entityManager->flush();

            $this->logger->info('Database started successfully', [
                'database_id' => $database->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to start database', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            $database->setStatus(Database::STATUS_ERROR);
            $database->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Stop a running database container
     */
    public function stopDatabase(Database $database): bool
    {
        try {
            if (!$database->getContainerId()) {
                throw new \RuntimeException('Container ID not found');
            }

            $command = sprintf('docker stop %s', escapeshellarg($database->getContainerId()));
            $this->executeCommand($command, $database->getServer());

            $database->setStatus(Database::STATUS_STOPPED);
            $database->setStoppedAt(new \DateTime());
            $database->setErrorMessage(null);
            $this->entityManager->flush();

            $this->logger->info('Database stopped successfully', [
                'database_id' => $database->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop database', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            $database->setStatus(Database::STATUS_ERROR);
            $database->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Delete a database container
     */
    public function deleteDatabase(Database $database): bool
    {
        try {
            $database->setStatus(Database::STATUS_DELETING);
            $this->entityManager->flush();

            if ($database->getContainerId()) {
                // Stop container if running
                if ($database->isRunning()) {
                    $this->stopDatabase($database);
                }

                // Remove container
                $command = sprintf('docker rm -f %s', escapeshellarg($database->getContainerId()));
                $this->executeCommand($command, $database->getServer());

                // Remove volume if exists
                $volumeName = $database->getContainerName() . '_data';
                $command = sprintf('docker volume rm %s 2>/dev/null || true', escapeshellarg($volumeName));
                $this->executeCommand($command, $database->getServer());
            }

            // Delete from database
            $this->entityManager->remove($database);
            $this->entityManager->flush();

            $this->logger->info('Database deleted successfully', [
                'database_id' => $database->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete database', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            $database->setStatus(Database::STATUS_ERROR);
            $database->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Restart a database container
     */
    public function restartDatabase(Database $database): bool
    {
        try {
            if (!$database->getContainerId()) {
                throw new \RuntimeException('Container ID not found');
            }

            $command = sprintf('docker restart %s', escapeshellarg($database->getContainerId()));
            $this->executeCommand($command, $database->getServer());

            $database->setStartedAt(new \DateTime());
            $database->setErrorMessage(null);
            $this->entityManager->flush();

            $this->logger->info('Database restarted successfully', [
                'database_id' => $database->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to restart database', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fix remote access for existing database
     */
    public function fixRemoteAccess(Database $database): bool
    {
        try {
            $this->logger->info('Fixing remote access for database', [
                'database_id' => $database->getId(),
                'type' => $database->getType(),
            ]);

            // Initialize remote access based on type
            $this->initializeDatabase($database);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fix remote access', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get database container status
     */
    public function getContainerStatus(Database $database): array
    {
        try {
            if (!$database->getContainerId()) {
                return ['status' => 'unknown', 'uptime' => null];
            }

            $command = sprintf(
                'docker inspect --format="{{.State.Status}}|{{.State.StartedAt}}" %s',
                escapeshellarg($database->getContainerId())
            );

            $output = $this->executeCommand($command, $database->getServer());
            [$status, $startedAt] = explode('|', trim($output));

            return [
                'status' => $status,
                'started_at' => $startedAt,
                'uptime' => $database->getUptime(),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Create Docker container for database
     */
    private function createDockerContainer(Database $database): string
    {
        $containerName = $database->getContainerName();
        $type = $database->getType();
        $version = $database->getVersion() ?? 'latest';
        $port = $database->getPort();
        $password = $database->getPassword();
        $username = $database->getUsername();
        $databaseName = $database->getDatabaseName() ?? $database->getName();

        // Build resource limits string (must come BEFORE image name in docker run)
        $resourceLimits = '';
        if ($database->getMemorySizeMb()) {
            $resourceLimits .= sprintf('-m %dm ', $database->getMemorySizeMb());
        }
        if ($database->getCpuLimit()) {
            $resourceLimits .= sprintf('--cpus=%s ', $database->getCpuLimit());
        }

        // Build Docker run command based on database type
        $command = match ($type) {
            Database::TYPE_POSTGRESQL => sprintf(
                'docker run -d --name %s --network pushify-network -p 0.0.0.0:%d:5432 ' .
                '%s' . // Resource limits
                '-e POSTGRES_USER=%s -e POSTGRES_PASSWORD=%s -e POSTGRES_DB=%s ' .
                '-e POSTGRES_HOST_AUTH_METHOD=md5 ' .
                '-v %s:/var/lib/postgresql/data ' .
                '--restart unless-stopped ' .
                'postgres:%s -c listen_addresses="*"',
                escapeshellarg($containerName),
                $port,
                $resourceLimits,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($databaseName),
                escapeshellarg($containerName . '_data'),
                $version  // Don't escape version - it's part of image tag
            ),

            Database::TYPE_MYSQL => sprintf(
                'docker run -d --name %s --network pushify-network -p 0.0.0.0:%d:3306 ' .
                '%s' . // Resource limits
                '-e MYSQL_ROOT_PASSWORD=%s -e MYSQL_DATABASE=%s -e MYSQL_USER=%s -e MYSQL_PASSWORD=%s ' .
                '-v %s:/var/lib/mysql ' .
                '--restart unless-stopped ' .
                'mysql:%s --bind-address=0.0.0.0',
                escapeshellarg($containerName),
                $port,
                $resourceLimits,
                escapeshellarg($password),
                escapeshellarg($databaseName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($containerName . '_data'),
                $version  // Don't escape version - it's part of image tag
            ),

            Database::TYPE_MARIADB => sprintf(
                'docker run -d --name %s --network pushify-network -p 0.0.0.0:%d:3306 ' .
                '%s' . // Resource limits
                '-e MARIADB_ROOT_PASSWORD=%s -e MARIADB_DATABASE=%s -e MARIADB_USER=%s -e MARIADB_PASSWORD=%s ' .
                '-v %s:/var/lib/mysql ' .
                '--restart unless-stopped ' .
                'mariadb:%s --bind-address=0.0.0.0',
                escapeshellarg($containerName),
                $port,
                $resourceLimits,
                escapeshellarg($password),
                escapeshellarg($databaseName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($containerName . '_data'),
                $version  // Don't escape version - it's part of image tag
            ),

            Database::TYPE_MONGODB => sprintf(
                'docker run -d --name %s --network pushify-network -p 0.0.0.0:%d:27017 ' .
                '%s' . // Resource limits
                '-e MONGO_INITDB_ROOT_USERNAME=%s -e MONGO_INITDB_ROOT_PASSWORD=%s ' .
                '-v %s:/data/db ' .
                '--restart unless-stopped ' .
                'mongo:%s --bind_ip_all',
                escapeshellarg($containerName),
                $port,
                $resourceLimits,
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($containerName . '_data'),
                $version  // Don't escape version - it's part of image tag
            ),

            Database::TYPE_REDIS => sprintf(
                'docker run -d --name %s --network pushify-network -p 0.0.0.0:%d:6379 ' .
                '%s' . // Resource limits
                '--restart unless-stopped ' .
                'redis:%s redis-server --requirepass %s --bind 0.0.0.0 --protected-mode no',
                escapeshellarg($containerName),
                $port,
                $resourceLimits,
                $version,  // Don't escape version - it's part of image tag
                escapeshellarg($password)
            ),

            default => throw new \RuntimeException('Unsupported database type: ' . $type),
        };

        // Execute command
        $output = $this->executeCommand($command, $database->getServer());

        return trim($output);
    }

    /**
     * Start a container
     */
    private function startContainer(Database $database): void
    {
        $command = sprintf('docker start %s', escapeshellarg($database->getContainerId()));
        $this->executeCommand($command, $database->getServer());
    }

    /**
     * Initialize database (create database, users, etc.)
     */
    private function initializeDatabase(Database $database): void
    {
        try {
            $type = $database->getType();

            // Grant remote access permissions
            match ($type) {
                Database::TYPE_MYSQL, Database::TYPE_MARIADB => $this->initializeMySQLRemoteAccess($database),
                Database::TYPE_POSTGRESQL => $this->initializePostgreSQLRemoteAccess($database),
                default => null, // MongoDB and Redis already configured
            };

            $this->logger->info('Database initialized with remote access', [
                'database_id' => $database->getId(),
                'type' => $database->getType(),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to initialize remote access', [
                'database_id' => $database->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize MySQL/MariaDB remote access
     */
    private function initializeMySQLRemoteAccess(Database $database): void
    {
        $username = $database->getUsername();
        $password = $database->getPassword();
        $databaseName = $database->getDatabaseName();
        $containerId = $database->getContainerId();
        $rootPassword = $database->getPassword();

        $this->logger->info('Waiting for MySQL container to be running', [
            'database_id' => $database->getId(),
            'container_id' => $containerId,
        ]);

        // Wait for container to be in running state (not restarting)
        $maxWaitAttempts = 60; // 60 seconds max
        $waitAttempt = 0;
        $containerRunning = false;

        while ($waitAttempt < $maxWaitAttempts && !$containerRunning) {
            try {
                // Check container state
                $stateCommand = sprintf(
                    'docker inspect -f "{{.State.Running}}" %s',
                    escapeshellarg($containerId)
                );

                $output = trim($this->executeCommand($stateCommand, $database->getServer()));

                if ($output === 'true') {
                    $containerRunning = true;
                    $this->logger->info('MySQL container is running', [
                        'database_id' => $database->getId(),
                        'wait_seconds' => $waitAttempt,
                    ]);
                } else {
                    $waitAttempt++;
                    sleep(1);
                }
            } catch (\Exception $e) {
                $waitAttempt++;
                sleep(1);
            }
        }

        if (!$containerRunning) {
            throw new \RuntimeException(sprintf(
                'MySQL container did not start after %d seconds',
                $maxWaitAttempts
            ));
        }

        // Additional wait for MySQL to complete initialization
        sleep(5);

        // Check if MySQL is ready by looking for "ready for connections" in logs
        $maxAttempts = 30;
        $attempt = 0;
        $ready = false;

        while ($attempt < $maxAttempts && !$ready) {
            try {
                // Check MySQL logs for ready message (suppress errors)
                $logCommand = sprintf(
                    'docker logs %s 2>&1 | grep -q "ready for connections" && echo "ready"',
                    escapeshellarg($containerId)
                );

                $output = trim($this->executeCommand($logCommand, $database->getServer()));

                if ($output === 'ready') {
                    $ready = true;
                    $this->logger->info('MySQL is ready for connections', [
                        'database_id' => $database->getId(),
                        'attempts' => $attempt + 1,
                    ]);
                }
            } catch (\Exception $e) {
                // Continue waiting
            }

            if (!$ready) {
                $attempt++;
                if ($attempt < $maxAttempts) {
                    sleep(1);
                } else {
                    $this->logger->warning('MySQL readiness check timed out, proceeding anyway', [
                        'database_id' => $database->getId(),
                    ]);
                    break;
                }
            }
        }

        // Additional wait to ensure MySQL is fully ready
        sleep(3);

        // Grant privileges to user from any host with retry logic
        // MySQL 8.0+ and MariaDB: Use mysql_native_password for better compatibility with older clients
        // Drop and recreate user to ensure authentication method is updated
        $sql = sprintf(
            "DROP USER IF EXISTS '%s'@'%%'; CREATE USER '%s'@'%%' IDENTIFIED WITH mysql_native_password BY '%s'; GRANT ALL PRIVILEGES ON %s.* TO '%s'@'%%'; FLUSH PRIVILEGES;",
            $username,
            $username,
            $password,
            $databaseName,
            $username
        );

        $grantSuccess = false;
        $grantAttempts = 0;
        $maxGrantAttempts = 10;

        while (!$grantSuccess && $grantAttempts < $maxGrantAttempts) {
            try {
                $command = sprintf(
                    'docker exec %s mysql -uroot -p%s -e %s',
                    escapeshellarg($containerId),
                    escapeshellarg($rootPassword),
                    escapeshellarg($sql)
                );

                $this->executeCommand($command, $database->getServer());
                $grantSuccess = true;

                $this->logger->info('MySQL remote access configured successfully', [
                    'database_id' => $database->getId(),
                    'grant_attempts' => $grantAttempts + 1,
                ]);
            } catch (\Exception $e) {
                $grantAttempts++;

                if ($grantAttempts < $maxGrantAttempts) {
                    $this->logger->info('Failed to configure MySQL remote access, retrying', [
                        'database_id' => $database->getId(),
                        'attempt' => $grantAttempts,
                        'max_attempts' => $maxGrantAttempts,
                    ]);
                    sleep(3);
                } else {
                    // Log warning but don't throw exception - database is created, user can fix manually
                    $this->logger->warning('Failed to configure MySQL remote access after all attempts', [
                        'database_id' => $database->getId(),
                        'attempts' => $maxGrantAttempts,
                        'error' => $e->getMessage(),
                    ]);
                    return;
                }
            }
        }
    }

    /**
     * Initialize PostgreSQL remote access
     */
    private function initializePostgreSQLRemoteAccess(Database $database): void
    {
        $containerId = $database->getContainerId();

        // Wait for PostgreSQL to be fully ready
        sleep(5);

        // Check if pg_hba.conf already has the rule
        $checkCommand = sprintf(
            'docker exec %s bash -c "grep -q \'host all all 0.0.0.0/0 md5\' /var/lib/postgresql/data/pg_hba.conf || echo \'host all all 0.0.0.0/0 md5\' >> /var/lib/postgresql/data/pg_hba.conf"',
            escapeshellarg($containerId)
        );
        $this->executeCommand($checkCommand, $database->getServer());

        // Reload PostgreSQL configuration using the actual username (not 'postgres')
        // Use docker restart to apply pg_hba.conf changes
        $reloadCommand = sprintf(
            'docker restart %s',
            escapeshellarg($containerId)
        );
        $this->executeCommand($reloadCommand, $database->getServer());

        // Wait for PostgreSQL to come back up
        sleep(5);

        $this->logger->info('PostgreSQL remote access configured', [
            'database_id' => $database->getId(),
        ]);
    }

    /**
     * Generate unique container name
     */
    private function generateContainerName(Database $database): string
    {
        $projectSlug = $database->getProject()->getSlug();
        $sanitizedName = preg_replace('/[^a-z0-9-]/', '-', strtolower($database->getName()));
        $uniqueId = substr(md5(uniqid()), 0, 8);

        return sprintf('db-%s-%s-%s', $projectSlug, $sanitizedName, $uniqueId);
    }

    /**
     * Find an available port
     */
    private function findAvailablePort(?Server $server): int
    {
        // Generate random port in range 10000-65000
        $basePort = rand(10000, 65000);

        // Check if port is available
        for ($port = $basePort; $port < 65535; $port++) {
            if ($this->isPortAvailable($port, $server)) {
                return $port;
            }
        }

        throw new \RuntimeException('No available ports found');
    }

    /**
     * Check if port is available
     */
    private function isPortAvailable(int $port, ?Server $server): bool
    {
        try {
            $command = sprintf('nc -z localhost %d', $port);
            $this->executeCommand($command, $server);
            return false; // Port is in use
        } catch (\Exception $e) {
            return true; // Port is available
        }
    }

    /**
     * Ensure pushify-network exists
     */
    private function ensureNetworkExists(?Server $server): void
    {
        try {
            // Check if network exists
            $checkCommand = 'docker network ls --filter name=pushify-network --format "{{.Name}}"';
            $output = $this->executeCommand($checkCommand, $server);

            if (trim($output) !== 'pushify-network') {
                // Create network
                $createCommand = 'docker network create pushify-network';
                $this->executeCommand($createCommand, $server);

                $this->logger->info('Created pushify-network', [
                    'server' => $server ? $server->getName() : 'local',
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to ensure network exists', [
                'error' => $e->getMessage(),
            ]);
            // Continue anyway - the docker run command will fail if network is really missing
        }
    }

    /**
     * Open firewall port on remote server
     */
    private function openFirewallPort(Server $server, int $port): void
    {
        try {
            // Check if UFW is installed and active
            $ufwStatus = $this->sshService->executeCommand($server, 'which ufw && ufw status | grep -q "Status: active" && echo "active" || echo "inactive"', 10);

            if (str_contains(trim($ufwStatus), 'active')) {
                // UFW is active, use it
                $this->logger->info('Opening port with UFW', [
                    'server' => $server->getName(),
                    'port' => $port,
                ]);

                $this->sshService->executeCommand($server, "ufw allow $port/tcp", 30);
            } else {
                // Try iptables
                $this->logger->info('Opening port with iptables', [
                    'server' => $server->getName(),
                    'port' => $port,
                ]);

                $this->sshService->executeCommand(
                    $server,
                    "iptables -C INPUT -p tcp --dport $port -j ACCEPT 2>/dev/null || iptables -A INPUT -p tcp --dport $port -j ACCEPT",
                    30
                );

                // Save iptables rules
                $this->sshService->executeCommand($server, 'iptables-save > /etc/iptables/rules.v4 2>/dev/null || true', 10);
            }

            $this->logger->info('Port opened successfully', [
                'server' => $server->getName(),
                'port' => $port,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to open firewall port', [
                'server' => $server->getName(),
                'port' => $port,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - database still works internally
        }
    }

    /**
     * Execute command on server or locally
     */
    private function executeCommand(string $command, ?Server $server): string
    {
        // Execute on remote server via SSH if server is configured
        if ($server) {
            $this->logger->info('Executing command on remote server', [
                'server' => $server->getName(),
                'command' => substr($command, 0, 100),
            ]);
            return $this->sshService->executeCommand($server, $command);
        }

        // Execute locally
        $this->logger->debug('Executing command locally', [
            'command' => substr($command, 0, 100),
        ]);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
