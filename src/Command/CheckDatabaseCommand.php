<?php

namespace App\Command;

use App\Repository\DatabaseRepository;
use App\Service\SshService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:database:check',
    description: 'Check database container status and logs',
)]
class CheckDatabaseCommand extends Command
{
    public function __construct(
        private DatabaseRepository $databaseRepository,
        private SshService $sshService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('database_id', InputArgument::REQUIRED, 'Database ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $databaseId = $input->getArgument('database_id');

        $database = $this->databaseRepository->find($databaseId);

        if (!$database) {
            $io->error('Database not found');
            return Command::FAILURE;
        }

        $io->title('Database Status Check');
        $io->info('Database: ' . $database->getName());
        $io->info('Type: ' . $database->getType());
        $io->info('Container ID: ' . $database->getContainerId());

        $server = $database->getServer();

        if (!$server) {
            $io->error('Server not found');
            return Command::FAILURE;
        }

        // Check container status
        $io->section('Container Status');
        try {
            $statusCmd = sprintf('docker inspect %s --format "{{.State.Status}}"', escapeshellarg($database->getContainerId()));
            $status = trim($this->sshService->executeCommand($server, $statusCmd));
            $io->info('Status: ' . $status);

            $restartingCmd = sprintf('docker inspect %s --format "{{.State.Restarting}}"', escapeshellarg($database->getContainerId()));
            $restarting = trim($this->sshService->executeCommand($server, $restartingCmd));
            $io->info('Restarting: ' . $restarting);

            $restartCountCmd = sprintf('docker inspect %s --format "{{.RestartCount}}"', escapeshellarg($database->getContainerId()));
            $restartCount = trim($this->sshService->executeCommand($server, $restartCountCmd));
            $io->info('Restart Count: ' . $restartCount);
        } catch (\Exception $e) {
            $io->error('Failed to get status: ' . $e->getMessage());
        }

        // Get container logs
        $io->section('Container Logs (last 50 lines)');
        try {
            $logsCmd = sprintf('docker logs %s --tail 50 2>&1', escapeshellarg($database->getContainerId()));
            $logs = $this->sshService->executeCommand($server, $logsCmd);
            $io->text($logs);
        } catch (\Exception $e) {
            $io->error('Failed to get logs: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
