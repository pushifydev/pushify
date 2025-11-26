<?php

namespace App\Service;

use App\Entity\Backup;
use App\Entity\Database;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BackupService
{
    private string $backupDir;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SshService $sshService,
        string $projectDir
    ) {
        $this->backupDir = $projectDir . '/var/backups';

        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a database backup
     */
    public function createBackup(Backup $backup): bool
    {
        try {
            $backup->setStatus(Backup::STATUS_CREATING);
            $this->entityManager->flush();

            $database = $backup->getDatabase();

            // Generate backup filename
            $filename = $backup->generateBackupFilename();
            $localPath = $this->backupDir . '/' . $filename;
            $backup->setFilePath($localPath);

            // Create backup based on database type
            $this->performBackup($database, $localPath);

            // Get file size
            if (file_exists($localPath)) {
                $fileSize = filesize($localPath);
                $backup->setFileSizeBytes((string)$fileSize);
            }

            // Calculate expiration date
            $backup->calculateExpiresAt();

            $backup->setStatus(Backup::STATUS_COMPLETED);
            $backup->setCompletedAt(new \DateTime());
            $backup->setErrorMessage(null);
            $this->entityManager->flush();

            $this->logger->info('Backup created successfully', [
                'backup_id' => $backup->getId(),
                'database_id' => $database->getId(),
                'file_path' => $localPath,
                'file_size' => $backup->getFileSizeMb() . ' MB',
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create backup', [
                'backup_id' => $backup->getId(),
                'error' => $e->getMessage(),
            ]);

            $backup->setStatus(Backup::STATUS_FAILED);
            $backup->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Restore a backup
     */
    public function restoreBackup(Backup $backup): bool
    {
        try {
            if (!$backup->isCompleted()) {
                throw new \RuntimeException('Backup is not completed or is corrupted');
            }

            if (!file_exists($backup->getFilePath())) {
                throw new \RuntimeException('Backup file not found');
            }

            $backup->setStatus(Backup::STATUS_RESTORING);
            $this->entityManager->flush();

            $database = $backup->getDatabase();

            // Perform restore based on database type
            $this->performRestore($database, $backup->getFilePath());

            $backup->setStatus(Backup::STATUS_RESTORED);
            $backup->setRestoredAt(new \DateTime());
            $this->entityManager->flush();

            $this->logger->info('Backup restored successfully', [
                'backup_id' => $backup->getId(),
                'database_id' => $database->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to restore backup', [
                'backup_id' => $backup->getId(),
                'error' => $e->getMessage(),
            ]);

            $backup->setStatus(Backup::STATUS_FAILED);
            $backup->setErrorMessage('Restore failed: ' . $e->getMessage());
            $this->entityManager->flush();

            return false;
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(Backup $backup): bool
    {
        try {
            // Delete backup file
            if ($backup->getFilePath() && file_exists($backup->getFilePath())) {
                unlink($backup->getFilePath());
            }

            // Mark as deleted or remove from database
            $this->entityManager->remove($backup);
            $this->entityManager->flush();

            $this->logger->info('Backup deleted successfully', [
                'backup_id' => $backup->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete backup', [
                'backup_id' => $backup->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Perform backup based on database type
     */
    private function performBackup(Database $database, string $outputPath): void
    {
        $server = $database->getServer();
        $tempFile = null;

        try {
            if ($server) {
                // Remote backup - create on server then download
                $tempFile = '/tmp/' . basename($outputPath);
                $command = $this->getBackupCommand($database, $tempFile);

                $this->logger->info('Creating remote backup', [
                    'database' => $database->getName(),
                    'server' => $server->getName(),
                    'command' => substr($command, 0, 100),
                ]);

                $this->sshService->executeCommand($server, $command, 600);

                // Download backup file from server
                $this->sshService->copyFileFromServer($server, $tempFile, $outputPath);

                // Clean up remote temp file
                $this->sshService->executeCommand($server, "rm -f {$tempFile}");
            } else {
                // Local backup
                $command = $this->getBackupCommand($database, $outputPath);

                $this->logger->info('Creating local backup', [
                    'database' => $database->getName(),
                    'command' => substr($command, 0, 100),
                ]);

                $process = Process::fromShellCommandline($command);
                $process->setTimeout(600);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException('Backup file was not created or is empty');
            }
        } catch (\Exception $e) {
            // Clean up on failure
            if ($server && $tempFile) {
                try {
                    $this->sshService->executeCommand($server, "rm -f {$tempFile}");
                } catch (\Exception $cleanupError) {
                    // Ignore cleanup errors
                }
            }
            throw $e;
        }
    }

    /**
     * Perform restore based on database type
     */
    private function performRestore(Database $database, string $backupPath): void
    {
        $server = $database->getServer();
        $tempFile = null;

        try {
            if ($server) {
                // Upload backup to server then restore
                $tempFile = '/tmp/' . basename($backupPath);
                $this->sshService->copyFileToServer($server, $backupPath, $tempFile);

                $command = $this->getRestoreCommand($database, $tempFile);

                $this->logger->info('Restoring remote backup', [
                    'database' => $database->getName(),
                    'server' => $server->getName(),
                ]);

                $this->sshService->executeCommand($server, $command, 600);

                // Clean up remote temp file
                $this->sshService->executeCommand($server, "rm -f {$tempFile}");
            } else {
                // Local restore
                $command = $this->getRestoreCommand($database, $backupPath);

                $this->logger->info('Restoring local backup', [
                    'database' => $database->getName(),
                ]);

                $process = Process::fromShellCommandline($command);
                $process->setTimeout(600);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }
        } catch (\Exception $e) {
            // Clean up on failure
            if ($server && $tempFile) {
                try {
                    $this->sshService->executeCommand($server, "rm -f {$tempFile}");
                } catch (\Exception $cleanupError) {
                    // Ignore cleanup errors
                }
            }
            throw $e;
        }
    }

    /**
     * Get backup command based on database type
     */
    private function getBackupCommand(Database $database, string $outputPath): string
    {
        $type = $database->getType();
        $containerName = $database->getContainerName();
        $username = $database->getUsername();
        $password = $database->getPassword();
        $databaseName = $database->getDatabaseName() ?? $database->getName();

        return match ($type) {
            Database::TYPE_POSTGRESQL => sprintf(
                'docker exec %s pg_dump -U %s -d %s | gzip > %s',
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($databaseName),
                escapeshellarg($outputPath)
            ),

            Database::TYPE_MYSQL, Database::TYPE_MARIADB => sprintf(
                'docker exec %s mysqldump -u %s -p%s %s | gzip > %s',
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($databaseName),
                escapeshellarg($outputPath)
            ),

            Database::TYPE_MONGODB => sprintf(
                'docker exec %s mongodump --username=%s --password=%s --db=%s --archive | gzip > %s',
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($databaseName),
                escapeshellarg($outputPath)
            ),

            Database::TYPE_REDIS => sprintf(
                'docker exec %s redis-cli --pass %s --rdb /tmp/dump.rdb SAVE && docker cp %s:/tmp/dump.rdb %s',
                escapeshellarg($containerName),
                escapeshellarg($password),
                escapeshellarg($containerName),
                escapeshellarg($outputPath)
            ),

            default => throw new \RuntimeException('Unsupported database type for backup: ' . $type),
        };
    }

    /**
     * Get restore command based on database type
     */
    private function getRestoreCommand(Database $database, string $backupPath): string
    {
        $type = $database->getType();
        $containerName = $database->getContainerName();
        $username = $database->getUsername();
        $password = $database->getPassword();
        $databaseName = $database->getDatabaseName() ?? $database->getName();

        return match ($type) {
            Database::TYPE_POSTGRESQL => sprintf(
                'gunzip -c %s | docker exec -i %s psql -U %s -d %s',
                escapeshellarg($backupPath),
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($databaseName)
            ),

            Database::TYPE_MYSQL, Database::TYPE_MARIADB => sprintf(
                'gunzip -c %s | docker exec -i %s mysql -u %s -p%s %s',
                escapeshellarg($backupPath),
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($databaseName)
            ),

            Database::TYPE_MONGODB => sprintf(
                'gunzip -c %s | docker exec -i %s mongorestore --username=%s --password=%s --db=%s --archive',
                escapeshellarg($backupPath),
                escapeshellarg($containerName),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($databaseName)
            ),

            Database::TYPE_REDIS => sprintf(
                'docker cp %s %s:/tmp/dump.rdb && docker exec %s redis-cli --pass %s SHUTDOWN NOSAVE && docker exec %s redis-cli --pass %s DEBUG RELOAD',
                escapeshellarg($backupPath),
                escapeshellarg($containerName),
                escapeshellarg($containerName),
                escapeshellarg($password),
                escapeshellarg($containerName),
                escapeshellarg($password)
            ),

            default => throw new \RuntimeException('Unsupported database type for restore: ' . $type),
        };
    }

    /**
     * Clean up expired backups
     */
    public function cleanupExpiredBackups(): int
    {
        $backupRepository = $this->entityManager->getRepository(Backup::class);
        $expiredBackups = $backupRepository->findExpiredBackups();

        $count = 0;
        foreach ($expiredBackups as $backup) {
            if ($this->deleteBackup($backup)) {
                $count++;
            }
        }

        $this->logger->info('Cleaned up expired backups', ['count' => $count]);

        return $count;
    }

    /**
     * Get backup directory path
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }
}
