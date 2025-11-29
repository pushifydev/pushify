<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DatabaseRepository;
use App\Repository\DeploymentRepository;
use App\Repository\DomainRepository;
use App\Repository\ProjectRepository;
use App\Repository\ServerRepository;
use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private DomainRepository $domainRepository,
        private DatabaseRepository $databaseRepository,
        private DeploymentRepository $deploymentRepository,
        private ServerRepository $serverRepository,
        private AnalyticsService $analyticsService
    ) {
    }

    #[Route('', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get counts
        $totalProjects = $this->projectRepository->count(['owner' => $user]);
        $activeServers = $this->serverRepository->count(['status' => 'active']);
        $totalDatabases = $this->databaseRepository->count([]);

        // Get deployments this month
        $startOfMonth = new \DateTimeImmutable('first day of this month 00:00:00');
        $deploymentsThisMonth = $this->deploymentRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.project', 'p')
            ->where('p.owner = :owner')
            ->andWhere('d.createdAt >= :start')
            ->setParameter('owner', $user)
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Get recent projects (last 5)
        $recentProjects = $this->projectRepository->findBy(
            ['owner' => $user],
            ['updatedAt' => 'DESC'],
            5
        );

        return $this->render('dashboard/index.html.twig', [
            'totalProjects' => $totalProjects,
            'activeServers' => $activeServers,
            'deploymentsThisMonth' => $deploymentsThisMonth,
            'totalDatabases' => $totalDatabases,
            'recentProjects' => $recentProjects,
        ]);
    }

    // Databases
    #[Route('/databases', name: 'app_dashboard_databases')]
    public function databases(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        // Get all databases for user's projects
        $allDatabases = [];
        foreach ($projects as $project) {
            $databases = $this->databaseRepository->findByProject($project);
            foreach ($databases as $database) {
                $allDatabases[] = [
                    'database' => $database,
                    'project' => $project,
                ];
            }
        }

        return $this->render('dashboard/databases/index.html.twig', [
            'databases' => $allDatabases,
            'projects' => $projects,
        ]);
    }

    // Domains - Overview of all domains across all projects
    #[Route('/domains', name: 'app_dashboard_domains')]
    public function domains(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        // Get all domains for user's projects
        $allDomains = [];
        foreach ($projects as $project) {
            $domains = $this->domainRepository->findByProject($project);
            foreach ($domains as $domain) {
                $allDomains[] = [
                    'domain' => $domain,
                    'project' => $project,
                ];
            }
        }

        return $this->render('dashboard/domains/overview.html.twig', [
            'domains' => $allDomains,
            'projects' => $projects,
        ]);
    }

    // SSL Certificates
    #[Route('/ssl', name: 'app_dashboard_ssl')]
    public function ssl(): Response
    {
        return $this->render('dashboard/ssl/index.html.twig');
    }

    // Analytics
    #[Route('/analytics', name: 'app_dashboard_analytics')]
    public function analytics(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $stats = $this->analyticsService->getDashboardStats($user);
        $projectStats = $this->analyticsService->getProjectStats($user);

        return $this->render('dashboard/analytics/index.html.twig', [
            'stats' => $stats,
            'projectStats' => $projectStats,
        ]);
    }

    #[Route('/analytics/api/trends', name: 'app_analytics_trends')]
    public function analyticsTrends(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        return $this->json([
            'trends' => $this->analyticsService->getDeploymentTrends($user, $days),
        ]);
    }

    #[Route('/analytics/api/by-trigger', name: 'app_analytics_by_trigger')]
    public function analyticsByTrigger(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'data' => $this->analyticsService->getDeploymentsByTrigger($user),
        ]);
    }

    #[Route('/analytics/api/by-status', name: 'app_analytics_by_status')]
    public function analyticsByStatus(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'data' => $this->analyticsService->getDeploymentsByStatus($user),
        ]);
    }

    #[Route('/analytics/api/build-times', name: 'app_analytics_build_times')]
    public function analyticsBuildTimes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        return $this->json([
            'data' => $this->analyticsService->getBuildTimeTrends($user, $days),
        ]);
    }

    // Logs
    #[Route('/logs', name: 'app_dashboard_logs')]
    public function logs(): Response
    {
        return $this->render('dashboard/logs/index.html.twig');
    }

    // Team
    #[Route('/team', name: 'app_dashboard_team')]
    public function team(): Response
    {
        return $this->render('dashboard/team/index.html.twig');
    }

    // Settings
    #[Route('/settings', name: 'app_dashboard_settings')]
    public function settings(): Response
    {
        return $this->render('dashboard/settings/index.html.twig');
    }
}
