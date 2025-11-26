<?php

namespace App\MessageHandler;

use App\Entity\Backup;
use App\Message\CreateBackupMessage;
use App\Service\BackupService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateBackupMessageHandler
{
    public function __construct(
        private ManagerRegistry $registry,
        private BackupService $backupService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CreateBackupMessage $message): void
    {
        // Get fresh EntityManager
        $entityManager = $this->registry->getManager();

        // If EntityManager is closed, reset it
        if (!$entityManager->isOpen()) {
            $this->logger->warning('EntityManager was closed, resetting it');
            $this->registry->resetManager();
            $entityManager = $this->registry->getManager();
        }

        try {
            $backup = $entityManager->getRepository(Backup::class)->find($message->getBackupId());

            if (!$backup) {
                $this->logger->error('Backup not found for async creation', [
                    'backup_id' => $message->getBackupId(),
                ]);
                return;
            }

            $this->logger->info('Processing backup creation', [
                'backup_id' => $backup->getId(),
                'database_id' => $backup->getDatabase()->getId(),
            ]);

            $success = $this->backupService->createBackup($backup);

            if ($success) {
                $this->logger->info('Backup created successfully via async processing', [
                    'backup_id' => $backup->getId(),
                ]);
            } else {
                $this->logger->error('Backup creation failed via async processing', [
                    'backup_id' => $backup->getId(),
                    'error' => $backup->getErrorMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during backup creation', [
                'backup_id' => $message->getBackupId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If EntityManager is closed, reset it for next message
            if (!$entityManager->isOpen()) {
                $this->registry->resetManager();
            }

            throw $e;
        }
    }
}
