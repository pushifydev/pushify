<?php

namespace App\MessageHandler;

use App\Entity\Database;
use App\Message\CreateDatabaseMessage;
use App\Service\DatabaseService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateDatabaseMessageHandler
{
    public function __construct(
        private ManagerRegistry $registry,
        private DatabaseService $databaseService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(CreateDatabaseMessage $message): void
    {
        $databaseId = $message->getDatabaseId();

        // Get fresh EntityManager
        $entityManager = $this->registry->getManager();

        // If EntityManager is closed, reset it
        if (!$entityManager->isOpen()) {
            $this->logger->warning('EntityManager was closed, resetting it');
            $this->registry->resetManager();
            $entityManager = $this->registry->getManager();
        }

        try {
            $this->logger->info('Processing database creation', [
                'database_id' => $databaseId,
            ]);

            $database = $entityManager->getRepository(Database::class)->find($databaseId);

            if (!$database) {
                $this->logger->error('Database not found', [
                    'database_id' => $databaseId,
                ]);
                return;
            }

            // Create the database
            $success = $this->databaseService->createDatabase($database);

            if ($success) {
                $this->logger->info('Database created successfully via queue', [
                    'database_id' => $databaseId,
                    'name' => $database->getName(),
                ]);
            } else {
                $this->logger->error('Failed to create database via queue', [
                    'database_id' => $databaseId,
                    'error' => $database->getErrorMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during database creation', [
                'database_id' => $databaseId,
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
