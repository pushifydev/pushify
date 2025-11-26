<?php

namespace App\Service;

use App\Entity\Deployment;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeploymentRepository $deploymentRepository,
        private ProjectRepository $projectRepository,
        private ActivityLogRepository $activityLogRepository
    ) {
    }

    /**
     * Get overall dashboard stats for a user
     */
    public function getDashboardStats(User $user): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        if (empty($projectIds)) {
            return $this->getEmptyStats();
        }

        $conn = $this->entityManager->getConnection();

        // Total deployments
        $totalDeployments = $conn->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE project_id IN (?)',
            [$projectIds],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        // Successful deployments
        $successfulDeployments = $conn->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE project_id IN (?) AND status = ?',
            [$projectIds, Deployment::STATUS_SUCCESS],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        // Failed deployments
        $failedDeployments = $conn->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE project_id IN (?) AND status = ?',
            [$projectIds, Deployment::STATUS_FAILED],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        // Average build time (successful deployments only)
        $avgBuildTime = $conn->fetchOne(
            'SELECT AVG(build_duration) FROM deployments WHERE project_id IN (?) AND status = ? AND build_duration IS NOT NULL',
            [$projectIds, Deployment::STATUS_SUCCESS],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        // Average deploy time
        $avgDeployTime = $conn->fetchOne(
            'SELECT AVG(deploy_duration) FROM deployments WHERE project_id IN (?) AND status = ? AND deploy_duration IS NOT NULL',
            [$projectIds, Deployment::STATUS_SUCCESS],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        // Deployments this week
        $weekAgo = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');
        $deploymentsThisWeek = $conn->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE project_id IN (?) AND created_at >= ?',
            [$projectIds, $weekAgo],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        // Deployments today
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s');
        $deploymentsToday = $conn->fetchOne(
            'SELECT COUNT(*) FROM deployments WHERE project_id IN (?) AND created_at >= ?',
            [$projectIds, $today],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING]
        );

        $successRate = $totalDeployments > 0
            ? round(($successfulDeployments / $totalDeployments) * 100, 1)
            : 0;

        return [
            'totalProjects' => count($projects),
            'activeProjects' => count(array_filter($projects, fn($p) => $p->getStatus() === Project::STATUS_DEPLOYED)),
            'totalDeployments' => (int) $totalDeployments,
            'successfulDeployments' => (int) $successfulDeployments,
            'failedDeployments' => (int) $failedDeployments,
            'successRate' => $successRate,
            'avgBuildTime' => $avgBuildTime ? round((float) $avgBuildTime) : null,
            'avgDeployTime' => $avgDeployTime ? round((float) $avgDeployTime) : null,
            'avgTotalTime' => ($avgBuildTime && $avgDeployTime) ? round((float) $avgBuildTime + (float) $avgDeployTime) : null,
            'deploymentsThisWeek' => (int) $deploymentsThisWeek,
            'deploymentsToday' => (int) $deploymentsToday,
        ];
    }

    /**
     * Get deployment trends over time (last 30 days)
     */
    public function getDeploymentTrends(User $user, int $days = 30): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        if (empty($projectIds)) {
            return $this->getEmptyTrends($days);
        }

        $conn = $this->entityManager->getConnection();
        $startDate = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d');

        // Get daily deployment counts
        $sql = "
            SELECT
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed
            FROM deployments
            WHERE project_id IN (?) AND DATE(created_at) >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $results = $conn->fetchAllAssociative($sql, [
            Deployment::STATUS_SUCCESS,
            Deployment::STATUS_FAILED,
            $projectIds,
            $startDate
        ], [
            \Doctrine\DBAL\ParameterType::STRING,
            \Doctrine\DBAL\ParameterType::STRING,
            \Doctrine\DBAL\ArrayParameterType::INTEGER,
            \Doctrine\DBAL\ParameterType::STRING
        ]);

        // Fill in missing dates
        $trends = [];
        $resultMap = [];
        foreach ($results as $row) {
            $resultMap[$row['date']] = $row;
        }

        for ($i = $days; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            $trends[] = [
                'date' => $date,
                'label' => (new \DateTimeImmutable($date))->format('M d'),
                'total' => isset($resultMap[$date]) ? (int) $resultMap[$date]['total'] : 0,
                'successful' => isset($resultMap[$date]) ? (int) $resultMap[$date]['successful'] : 0,
                'failed' => isset($resultMap[$date]) ? (int) $resultMap[$date]['failed'] : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get deployment stats by project
     */
    public function getProjectStats(User $user): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $stats = [];

        foreach ($projects as $project) {
            $deployments = $this->deploymentRepository->findByProject($project, 100);
            $successful = count(array_filter($deployments, fn($d) => $d->isSuccess()));
            $failed = count(array_filter($deployments, fn($d) => $d->isFailed()));
            $total = count($deployments);

            $avgTime = null;
            $successfulWithTime = array_filter($deployments, fn($d) => $d->isSuccess() && $d->getTotalDuration());
            if (!empty($successfulWithTime)) {
                $totalTime = array_sum(array_map(fn($d) => $d->getTotalDuration(), $successfulWithTime));
                $avgTime = round($totalTime / count($successfulWithTime));
            }

            $lastDeployment = $deployments[0] ?? null;

            $stats[] = [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
                'status' => $project->getStatus(),
                'framework' => $project->getFramework(),
                'totalDeployments' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'successRate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0,
                'avgBuildTime' => $avgTime,
                'lastDeployedAt' => $lastDeployment?->getCreatedAt()?->format('c'),
                'lastStatus' => $lastDeployment?->getStatus(),
            ];
        }

        // Sort by total deployments
        usort($stats, fn($a, $b) => $b['totalDeployments'] <=> $a['totalDeployments']);

        return $stats;
    }

    /**
     * Get deployment breakdown by trigger type
     */
    public function getDeploymentsByTrigger(User $user): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        if (empty($projectIds)) {
            return [];
        }

        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT trigger, COUNT(*) as count
            FROM deployments
            WHERE project_id IN (?)
            GROUP BY trigger
        ";

        $results = $conn->fetchAllAssociative($sql, [$projectIds], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);

        $triggers = [];
        foreach ($results as $row) {
            $triggers[] = [
                'trigger' => $row['trigger'],
                'label' => $this->getTriggerLabel($row['trigger']),
                'count' => (int) $row['count'],
            ];
        }

        return $triggers;
    }

    /**
     * Get deployment breakdown by status
     */
    public function getDeploymentsByStatus(User $user): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        if (empty($projectIds)) {
            return [];
        }

        $conn = $this->entityManager->getConnection();

        $sql = "
            SELECT status, COUNT(*) as count
            FROM deployments
            WHERE project_id IN (?)
            GROUP BY status
        ";

        $results = $conn->fetchAllAssociative($sql, [$projectIds], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);

        $statuses = [];
        foreach ($results as $row) {
            $statuses[] = [
                'status' => $row['status'],
                'label' => ucfirst($row['status']),
                'count' => (int) $row['count'],
                'color' => $this->getStatusColor($row['status']),
            ];
        }

        return $statuses;
    }

    /**
     * Get build time trends
     */
    public function getBuildTimeTrends(User $user, int $days = 30): array
    {
        $projects = $this->projectRepository->findByOwner($user);
        $projectIds = array_map(fn($p) => $p->getId(), $projects);

        if (empty($projectIds)) {
            return [];
        }

        $conn = $this->entityManager->getConnection();
        $startDate = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d');

        $sql = "
            SELECT
                DATE(created_at) as date,
                AVG(build_duration) as avg_build,
                AVG(deploy_duration) as avg_deploy
            FROM deployments
            WHERE project_id IN (?)
              AND status = ?
              AND DATE(created_at) >= ?
              AND build_duration IS NOT NULL
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ";

        $results = $conn->fetchAllAssociative($sql, [
            $projectIds,
            Deployment::STATUS_SUCCESS,
            $startDate
        ], [
            \Doctrine\DBAL\ArrayParameterType::INTEGER,
            \Doctrine\DBAL\ParameterType::STRING,
            \Doctrine\DBAL\ParameterType::STRING
        ]);

        return array_map(fn($row) => [
            'date' => $row['date'],
            'label' => (new \DateTimeImmutable($row['date']))->format('M d'),
            'avgBuild' => $row['avg_build'] ? round((float) $row['avg_build']) : null,
            'avgDeploy' => $row['avg_deploy'] ? round((float) $row['avg_deploy']) : null,
        ], $results);
    }

    /**
     * Get activity stats
     */
    public function getActivityStats(User $user): array
    {
        $since = new \DateTimeImmutable('-30 days');
        return $this->activityLogRepository->getStatsForUser($user, $since);
    }

    private function getEmptyStats(): array
    {
        return [
            'totalProjects' => 0,
            'activeProjects' => 0,
            'totalDeployments' => 0,
            'successfulDeployments' => 0,
            'failedDeployments' => 0,
            'successRate' => 0,
            'avgBuildTime' => null,
            'avgDeployTime' => null,
            'avgTotalTime' => null,
            'deploymentsThisWeek' => 0,
            'deploymentsToday' => 0,
        ];
    }

    private function getEmptyTrends(int $days): array
    {
        $trends = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            $trends[] = [
                'date' => $date,
                'label' => (new \DateTimeImmutable($date))->format('M d'),
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
            ];
        }
        return $trends;
    }

    private function getTriggerLabel(string $trigger): string
    {
        return match ($trigger) {
            Deployment::TRIGGER_MANUAL => 'Manual',
            Deployment::TRIGGER_GIT_PUSH => 'Git Push',
            Deployment::TRIGGER_ROLLBACK => 'Rollback',
            Deployment::TRIGGER_REDEPLOY => 'Redeploy',
            default => ucfirst($trigger),
        };
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            Deployment::STATUS_SUCCESS => '#22c55e',
            Deployment::STATUS_FAILED => '#ef4444',
            Deployment::STATUS_BUILDING, Deployment::STATUS_DEPLOYING => '#eab308',
            Deployment::STATUS_QUEUED => '#3b82f6',
            Deployment::STATUS_CANCELLED => '#6b7280',
            default => '#6b7280',
        };
    }
}
