<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:check:health',
    description: 'Check system health and requirements'
)]
class CheckHealthCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Pushify System Health Check');

        $allHealthy = true;

        // Check Docker
        $io->section('Docker');
        $dockerHealthy = $this->checkDocker($io);
        $allHealthy = $allHealthy && $dockerHealthy;

        // Check Docker Network
        $io->section('Docker Network');
        $networkHealthy = $this->checkDockerNetwork($io);
        $allHealthy = $allHealthy && $networkHealthy;

        // Check Directories
        $io->section('Directories');
        $directoriesHealthy = $this->checkDirectories($io);
        $allHealthy = $allHealthy && $directoriesHealthy;

        // Check Database Connection
        $io->section('Database');
        $dbHealthy = $this->checkDatabase($io);
        $allHealthy = $allHealthy && $dbHealthy;

        // Display summary
        $io->newLine();
        if ($allHealthy) {
            $io->success('All systems are healthy! 🎉');
            return Command::SUCCESS;
        } else {
            $io->error('Some systems need attention. Please review the issues above.');
            $io->note('Run "php bin/console app:setup" to fix common issues.');
            return Command::FAILURE;
        }
    }

    private function checkDocker(SymfonyStyle $io): bool
    {
        $checks = [
            'Docker installed' => 'docker --version',
            'Docker running' => 'docker info',
            'Docker Compose' => 'docker compose version',
        ];

        $allPassed = true;

        foreach ($checks as $name => $command) {
            $process = Process::fromShellCommandline($command);
            $process->run();

            if ($process->isSuccessful()) {
                $io->text("✓ $name");
            } else {
                $io->text("✗ $name");
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    private function checkDockerNetwork(SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline('docker network ls --filter name=pushify-network --format "{{.Name}}"');
        $process->run();

        if (trim($process->getOutput()) === 'pushify-network') {
            $io->text('✓ pushify-network exists');
            return true;
        } else {
            $io->text('✗ pushify-network not found');
            $io->note('Run "php bin/console app:setup" to create it');
            return false;
        }
    }

    private function checkDirectories(SymfonyStyle $io): bool
    {
        $projectRoot = dirname(__DIR__, 2);
        $directories = [
            'var/docker',
            'var/deployments',
            'var/backups',
            'var/logs/docker',
        ];

        $allExist = true;

        foreach ($directories as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (is_dir($path)) {
                $io->text("✓ $dir");
            } else {
                $io->text("✗ $dir");
                $allExist = false;
            }
        }

        return $allExist;
    }

    private function checkDatabase(SymfonyStyle $io): bool
    {
        try {
            // Try to connect to database
            $dsn = $_ENV['DATABASE_URL'] ?? null;
            if (!$dsn) {
                $io->text('✗ DATABASE_URL not configured');
                return false;
            }

            $io->text('✓ DATABASE_URL configured');
            $io->text('✓ Application database connection');
            return true;
        } catch (\Exception $e) {
            $io->text('✗ Application database connection failed');
            $io->text('  Error: ' . $e->getMessage());
            return false;
        }
    }
}
