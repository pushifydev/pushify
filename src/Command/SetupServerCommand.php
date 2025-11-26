<?php

namespace App\Command;

use App\Repository\ServerRepository;
use App\Service\SshService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup:server',
    description: 'Setup Docker environment on remote server'
)]
class SetupServerCommand extends Command
{
    public function __construct(
        private ServerRepository $serverRepository,
        private SshService $sshService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('server-id', InputArgument::OPTIONAL, 'Server ID (if not provided, setup all servers)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $serverId = $input->getArgument('server-id');

        if ($serverId) {
            $server = $this->serverRepository->find($serverId);
            if (!$server) {
                $io->error('Server not found');
                return Command::FAILURE;
            }
            $servers = [$server];
        } else {
            $servers = $this->serverRepository->findAll();
        }

        if (empty($servers)) {
            $io->warning('No servers found. Add servers first.');
            return Command::SUCCESS;
        }

        foreach ($servers as $server) {
            $io->title('Setting up server: ' . $server->getName());

            // Test connection
            $io->section('Testing SSH connection...');
            if (!$this->sshService->testConnection($server)) {
                $io->error('SSH connection failed for server: ' . $server->getName());
                continue;
            }
            $io->success('SSH connection successful');

            // Check if Docker is installed
            $io->section('Checking Docker installation...');
            try {
                $output = $this->sshService->executeCommand($server, 'docker --version');
                $io->text('Docker version: ' . trim($output));
                $io->success('Docker is installed');
            } catch (\Exception $e) {
                $io->error('Docker is not installed on this server');
                $io->note('Install Docker first: curl -fsSL https://get.docker.com | sh');
                continue;
            }

            // Check if Docker is running
            $io->section('Checking Docker status...');
            try {
                $this->sshService->executeCommand($server, 'docker info', 10);
                $io->success('Docker is running');
            } catch (\Exception $e) {
                $io->error('Docker is not running');
                $io->note('Start Docker: sudo systemctl start docker');
                continue;
            }

            // Create pushify-network
            $io->section('Setting up Docker network...');
            try {
                // Check if network exists
                $networks = $this->sshService->executeCommand(
                    $server,
                    'docker network ls --filter name=pushify-network --format "{{.Name}}"'
                );

                if (trim($networks) === 'pushify-network') {
                    $io->warning('Docker network "pushify-network" already exists');
                } else {
                    $this->sshService->executeCommand($server, 'docker network create pushify-network');
                    $io->success('Docker network "pushify-network" created successfully');
                }
            } catch (\Exception $e) {
                $io->error('Failed to create Docker network: ' . $e->getMessage());
                continue;
            }

            // Create necessary directories
            $io->section('Creating directories...');
            $directories = [
                '/var/pushify/docker',
                '/var/pushify/deployments',
                '/var/pushify/backups',
                '/var/pushify/logs',
            ];

            foreach ($directories as $dir) {
                try {
                    $this->sshService->executeCommand($server, "mkdir -p $dir && chmod 755 $dir");
                    $io->text("Created: $dir");
                } catch (\Exception $e) {
                    $io->error("Failed to create $dir: " . $e->getMessage());
                }
            }

            // Display server info
            $io->section('Server Information');
            try {
                $osInfo = $this->sshService->executeCommand($server, 'cat /etc/os-release | grep "PRETTY_NAME" | cut -d= -f2 | tr -d \'"\'');
                $cpuInfo = $this->sshService->executeCommand($server, 'nproc');
                $memInfo = $this->sshService->executeCommand($server, 'free -h | grep Mem | awk \'{print $2}\'');
                $diskInfo = $this->sshService->executeCommand($server, 'df -h / | tail -1 | awk \'{print $2}\'');

                $io->table(
                    ['Property', 'Value'],
                    [
                        ['Operating System', trim($osInfo)],
                        ['CPU Cores', trim($cpuInfo)],
                        ['Total Memory', trim($memInfo)],
                        ['Disk Size', trim($diskInfo)],
                    ]
                );
            } catch (\Exception $e) {
                $io->warning('Could not retrieve server information');
            }

            $io->success('Server setup completed: ' . $server->getName());
            $io->newLine();
        }

        $io->success('All servers have been processed!');

        return Command::SUCCESS;
    }
}
