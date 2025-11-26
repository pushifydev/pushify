<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:setup',
    description: 'Initial setup for Pushify - Configure Docker and required infrastructure'
)]
class SetupCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Pushify Initial Setup');
        $io->section('Checking Docker installation...');

        // Check if Docker is installed
        if (!$this->checkDockerInstalled($io)) {
            $io->error('Docker is not installed. Please install Docker Desktop first.');
            $io->note('Download Docker Desktop from: https://www.docker.com/products/docker-desktop');
            return Command::FAILURE;
        }

        $io->success('Docker is installed');

        // Check if Docker is running
        if (!$this->checkDockerRunning($io)) {
            $io->error('Docker is not running. Please start Docker Desktop.');
            return Command::FAILURE;
        }

        $io->success('Docker is running');

        // Create Pushify Docker network
        $io->section('Setting up Docker network...');
        if ($this->createDockerNetwork($io)) {
            $io->success('Docker network "pushify-network" created successfully');
        } else {
            $io->warning('Docker network "pushify-network" already exists');
        }

        // Create necessary directories
        $io->section('Creating directories...');
        $this->createDirectories($io);

        // Check Docker Compose
        $io->section('Checking Docker Compose...');
        if ($this->checkDockerCompose($io)) {
            $io->success('Docker Compose is available');
        } else {
            $io->warning('Docker Compose is not available (optional)');
        }

        // Display system information
        $io->section('System Information');
        $this->displaySystemInfo($io);

        // Final summary
        $io->section('Setup Summary');
        $io->success([
            'Pushify setup completed successfully!',
            '',
            'You can now:',
            '• Create projects',
            '• Deploy applications',
            '• Create database containers',
            '• Manage your infrastructure',
        ]);

        $io->note([
            'Next steps:',
            '1. Configure your first server (optional)',
            '2. Create your first project',
            '3. Deploy your application',
            '',
            'Run "php bin/console app:check:health" to verify system status',
        ]);

        return Command::SUCCESS;
    }

    private function checkDockerInstalled(SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline('docker --version');
        $process->run();

        if ($process->isSuccessful()) {
            $io->text('Docker version: ' . trim($process->getOutput()));
            return true;
        }

        return false;
    }

    private function checkDockerRunning(SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline('docker info');
        $process->run();

        return $process->isSuccessful();
    }

    private function checkDockerCompose(SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline('docker compose version');
        $process->run();

        if ($process->isSuccessful()) {
            $io->text('Docker Compose version: ' . trim($process->getOutput()));
            return true;
        }

        return false;
    }

    private function createDockerNetwork(SymfonyStyle $io): bool
    {
        // Check if network already exists
        $checkProcess = Process::fromShellCommandline('docker network ls --filter name=pushify-network --format "{{.Name}}"');
        $checkProcess->run();

        if (trim($checkProcess->getOutput()) === 'pushify-network') {
            return false; // Already exists
        }

        // Create network
        $process = Process::fromShellCommandline('docker network create pushify-network');
        $process->run();

        return $process->isSuccessful();
    }

    private function createDirectories(SymfonyStyle $io): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $directories = [
            'var/docker',
            'var/deployments',
            'var/backups',
            'var/logs/docker',
        ];

        foreach ($directories as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $io->text("Created: $dir");
            } else {
                $io->text("Exists: $dir");
            }
        }
    }

    private function displaySystemInfo(SymfonyStyle $io): void
    {
        // Get Docker info
        $dockerInfoProcess = Process::fromShellCommandline('docker info --format "{{.OperatingSystem}}|{{.ServerVersion}}|{{.NCPU}}|{{.MemTotal}}"');
        $dockerInfoProcess->run();

        if ($dockerInfoProcess->isSuccessful()) {
            $info = explode('|', trim($dockerInfoProcess->getOutput()));

            $io->table(
                ['Property', 'Value'],
                [
                    ['Operating System', $info[0] ?? 'N/A'],
                    ['Docker Version', $info[1] ?? 'N/A'],
                    ['CPU Cores', $info[2] ?? 'N/A'],
                    ['Total Memory', $this->formatBytes((int)($info[3] ?? 0))],
                ]
            );
        }

        // Get running containers
        $containersProcess = Process::fromShellCommandline('docker ps --format "{{.Names}}" | wc -l');
        $containersProcess->run();

        if ($containersProcess->isSuccessful()) {
            $io->text('Running containers: ' . trim($containersProcess->getOutput()));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log(1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
