<?php

namespace App\Command;

use App\Service\BackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backup:cleanup',
    description: 'Clean up expired backups'
)]
class BackupCleanupCommand extends Command
{
    public function __construct(
        private BackupService $backupService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Backup Cleanup');
        $io->text('Cleaning up expired backups...');

        $count = $this->backupService->cleanupExpiredBackups();

        if ($count > 0) {
            $io->success("Cleaned up {$count} expired backup(s)");
        } else {
            $io->info('No expired backups found');
        }

        return Command::SUCCESS;
    }
}
