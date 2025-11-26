<?php

namespace App\Command;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\AlertService;
use App\Service\MonitoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monitor:health',
    description: 'Perform health checks on all deployed projects and trigger alerts if needed',
)]
class MonitorHealthCommand extends Command
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MonitoringService $monitoringService,
        private AlertService $alertService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('project-id', 'p', InputOption::VALUE_OPTIONAL, 'Check specific project by ID')
            ->setHelp('This command performs health checks on all deployed projects and triggers alerts based on configured rules.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Project Health Monitoring');

        $projectId = $input->getOption('project-id');

        if ($projectId) {
            $project = $this->projectRepository->find($projectId);
            if (!$project) {
                $io->error("Project with ID {$projectId} not found");
                return Command::FAILURE;
            }
            $projects = [$project];
        } else {
            // Get all deployed projects
            $projects = $this->projectRepository->findBy(['status' => Project::STATUS_DEPLOYED]);
        }

        if (empty($projects)) {
            $io->info('No deployed projects found');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Monitoring %d project(s)...', count($projects)));

        $successCount = 0;
        $failureCount = 0;
        $alertsTriggered = 0;

        foreach ($projects as $project) {
            $io->write(sprintf('Checking %s... ', $project->getName()));

            try {
                // Perform health check
                $healthCheck = $this->monitoringService->performHealthCheck($project);

                // Check alert rules
                $this->alertService->checkAlertRules($project, $healthCheck);

                // Auto-resolve alerts if conditions improved
                $this->alertService->autoResolveAlerts($project, $healthCheck);

                $statusIcon = match ($healthCheck->getStatus()) {
                    'healthy' => '✅',
                    'degraded' => '⚠️ ',
                    'down' => '❌',
                    default => '❓',
                };

                $io->writeln($statusIcon . ' ' . $healthCheck->getStatus());

                if ($healthCheck->isHealthy()) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $io->writeln('❌ Error: ' . $e->getMessage());
                $failureCount++;
            }
        }

        $io->newLine();
        $io->success(sprintf(
            'Health check completed: %d healthy, %d issues',
            $successCount,
            $failureCount
        ));

        return Command::SUCCESS;
    }
}
