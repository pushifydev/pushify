<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Entity\EnvironmentVariable;
use App\Repository\DeploymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\ServerRepository;
use App\Repository\EnvironmentVariableRepository;
use App\Service\GitHubService;
use App\Service\ContainerLogsService;
use App\Service\EnvironmentService;
use App\Service\MonitoringService;
use App\Service\AlertService;
use App\Service\DeploymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/dashboard/projects')]
#[IsGranted('ROLE_USER')]
class ProjectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private DeploymentRepository $deploymentRepository,
        private EnvironmentVariableRepository $envVarRepository,
        private GitHubService $gitHubService,
        private SluggerInterface $slugger,
        private ContainerLogsService $containerLogsService,
        private EnvironmentService $environmentService,
        private MonitoringService $monitoringService,
        private AlertService $alertService,
        private DeploymentService $deploymentService
    ) {
    }

    #[Route('', name: 'app_projects', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        return $this->render('dashboard/projects/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET'])]
    public function new(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get user's GitHub repositories
        $repositories = [];
        if ($user->getGithubAccessToken()) {
            $repositories = $this->gitHubService->getUserRepositories($user);
        }

        return $this->render('dashboard/projects/new.html.twig', [
            'repositories' => $repositories,
            'hasGithubToken' => (bool) $user->getGithubAccessToken(),
        ]);
    }

    #[Route('/new/configure', name: 'app_project_configure', methods: ['GET', 'POST'])]
    public function configure(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $repoFullName = $request->query->get('repo');
        if (!$repoFullName) {
            return $this->redirectToRoute('app_project_new');
        }

        [$owner, $repo] = explode('/', $repoFullName, 2);

        // Get repository details
        $repository = $this->gitHubService->getRepository($user, $owner, $repo);
        if (!$repository) {
            $this->addFlash('error', 'Could not fetch repository details');
            return $this->redirectToRoute('app_project_new');
        }

        // Get branches
        $branches = $this->gitHubService->getRepositoryBranches($user, $owner, $repo);

        // Detect framework
        $detectedFramework = $this->gitHubService->detectFramework($user, $owner, $repo) ?? 'other';
        $defaults = GitHubService::getFrameworkDefaults($detectedFramework);

        if ($request->isMethod('POST')) {
            $project = new Project();
            $project->setOwner($user);
            $project->setName($request->request->get('name', $repo));

            // Generate unique slug
            $baseSlug = strtolower($this->slugger->slug($request->request->get('name', $repo))->toString());
            $slug = $baseSlug;
            $counter = 1;
            while ($this->projectRepository->slugExists($slug)) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $project->setSlug($slug);

            $project->setDescription($repository['description'] ?? null);
            $project->setRepositoryUrl($repository['html_url']);
            $project->setRepositoryName($repoFullName);
            $project->setBranch($request->request->get('branch', $repository['default_branch']));
            $project->setFramework($request->request->get('framework', $detectedFramework));
            $project->setInstallCommand($request->request->get('installCommand', $defaults['installCommand']));
            $project->setBuildCommand($request->request->get('buildCommand', $defaults['buildCommand']));
            $project->setStartCommand($request->request->get('startCommand', 'npm start'));
            $project->setOutputDirectory($request->request->get('outputDirectory', $defaults['outputDirectory']));
            $project->setRootDirectory($request->request->get('rootDirectory', './'));

            // Generate production URL
            $project->setProductionUrl($slug . '.pushify.app');

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            // 🚀 AUTO-DEPLOY: Automatically trigger first deployment after project creation
            try {
                $deploymentService = $this->container->get(DeploymentService::class);
                $deployment = $deploymentService->createDeployment(
                    $project,
                    $user,
                    Deployment::TRIGGER_MANUAL,
                    null,
                    'Initial deployment from GitHub'
                );

                $this->addFlash('success', 'Project created successfully! Deployment started automatically.');
                return $this->redirectToRoute('app_deployment_show', [
                    'slug' => $project->getSlug(),
                    'id' => $deployment->getId()
                ]);
            } catch (\Exception $e) {
                // If deployment fails, still show project page
                $this->addFlash('success', 'Project created successfully!');
                $this->addFlash('warning', 'Could not start automatic deployment: ' . $e->getMessage());
                return $this->redirectToRoute('app_project_show', ['slug' => $project->getSlug()]);
            }
        }

        return $this->render('dashboard/projects/configure.html.twig', [
            'repository' => $repository,
            'branches' => $branches,
            'detectedFramework' => $detectedFramework,
            'defaults' => $defaults,
            'frameworks' => Project::getFrameworkChoices(),
        ]);
    }

    #[Route('/{slug}', name: 'app_project_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $deployments = $this->deploymentRepository->findByProject($project, 5);

        return $this->render('dashboard/projects/show.html.twig', [
            'project' => $project,
            'deployments' => $deployments,
        ]);
    }

    #[Route('/{slug}/settings', name: 'app_project_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if ($request->isMethod('POST')) {
            // Handle server selection
            $serverId = $request->request->get('server_id');
            if ($serverId !== null) {
                if ($serverId === '' || $serverId === '0') {
                    $project->setServer(null);
                } else {
                    $server = $this->serverRepository->findByOwnerAndId($user, (int) $serverId);
                    if ($server) {
                        $project->setServer($server);
                    }
                }
            }

            // Handle other settings
            if ($request->request->has('name')) {
                $project->setName($request->request->get('name', $project->getName()));
            }
            if ($request->request->has('branch')) {
                $project->setBranch($request->request->get('branch', $project->getBranch()));
            }
            if ($request->request->has('framework')) {
                $project->setFramework($request->request->get('framework', $project->getFramework()));
            }
            if ($request->request->has('installCommand')) {
                $project->setInstallCommand($request->request->get('installCommand'));
            }
            if ($request->request->has('buildCommand')) {
                $project->setBuildCommand($request->request->get('buildCommand'));
            }
            if ($request->request->has('startCommand')) {
                $project->setStartCommand($request->request->get('startCommand'));
            }
            if ($request->request->has('outputDirectory')) {
                $project->setOutputDirectory($request->request->get('outputDirectory'));
            }
            if ($request->request->has('rootDirectory')) {
                $project->setRootDirectory($request->request->get('rootDirectory'));
            }
            // Handle toggle settings - checkboxes need special handling (unchecked = not sent)
            if ($request->request->has('name')) {
                // This form has the toggle checkboxes
                $project->setAutoDeployEnabled($request->request->has('autoDeployEnabled'));
                $project->setPreviewDeploymentsEnabled($request->request->has('previewDeploymentsEnabled'));
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Settings saved successfully!');
            return $this->redirectToRoute('app_project_settings', ['slug' => $project->getSlug()]);
        }

        // Get user's servers for server selection
        $servers = $this->serverRepository->findByOwner($user);

        return $this->render('dashboard/projects/settings.html.twig', [
            'project' => $project,
            'frameworks' => Project::getFrameworkChoices(),
            'servers' => $servers,
        ]);
    }

    #[Route('/{slug}/delete', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if ($this->isCsrfTokenValid('delete-project-' . $project->getId(), $request->request->get('_token'))) {
            // Stop and remove container on remote server before deleting project
            try {
                $this->deploymentService->cleanupProjectContainers($project);
            } catch (\Exception $e) {
                // Log error but continue with deletion - container might already be gone
                error_log('Failed to cleanup containers for project ' . $project->getSlug() . ': ' . $e->getMessage());
            }

            $this->entityManager->remove($project);
            $this->entityManager->flush();
            $this->addFlash('success', 'Project deleted successfully');
        }

        return $this->redirectToRoute('app_projects');
    }

    #[Route('/api/repositories', name: 'app_project_api_repositories', methods: ['GET'])]
    public function apiRepositories(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getGithubAccessToken()) {
            return $this->json(['error' => 'GitHub not connected'], Response::HTTP_UNAUTHORIZED);
        }

        $repositories = $this->gitHubService->getUserRepositories($user);

        return $this->json($repositories);
    }

    #[Route('/api/detect-framework', name: 'app_project_api_detect_framework', methods: ['GET'])]
    public function apiDetectFramework(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $repoFullName = $request->query->get('repo');
        if (!$repoFullName) {
            return $this->json(['error' => 'Repository not specified'], Response::HTTP_BAD_REQUEST);
        }

        [$owner, $repo] = explode('/', $repoFullName, 2);
        $framework = $this->gitHubService->detectFramework($user, $owner, $repo);
        $defaults = GitHubService::getFrameworkDefaults($framework ?? 'other');

        return $this->json([
            'framework' => $framework,
            'defaults' => $defaults,
        ]);
    }


    #[Route('/{slug}/logs', name: 'app_project_logs', methods: ['GET'])]
    public function logs(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $isRunning = $this->containerLogsService->isContainerRunning($project);

        return $this->render('dashboard/projects/logs.html.twig', [
            'project' => $project,
            'isRunning' => $isRunning,
        ]);
    }

    #[Route('/{slug}/logs/api', name: 'app_project_logs_api', methods: ['GET'])]
    public function logsApi(string $slug, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $tail = (int) $request->query->get('tail', 100);
        $tail = max(1, min($tail, 1000)); // Limit between 1 and 1000 lines

        $result = $this->containerLogsService->getLogs($project, $tail, false);

        return $this->json($result);
    }

    #[Route('/{slug}/logs/status', name: 'app_project_logs_status', methods: ['GET'])]
    public function logsStatus(string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $isRunning = $this->containerLogsService->isContainerRunning($project);
        $stats = [];

        if ($isRunning) {
            $statsResult = $this->containerLogsService->getContainerStats($project);
            if ($statsResult['success'] ?? false) {
                $stats = $statsResult['stats'];
            }
        }

        return $this->json([
            'success' => true,
            'isRunning' => $isRunning,
            'stats' => $stats,
        ]);
    }

    #[Route('/{slug}/environment', name: 'app_project_environment', methods: ['GET'])]
    public function environment(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        return $this->render('dashboard/projects/environment.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/environment/api', name: 'app_project_environment_api', methods: ['GET'])]
    public function environmentApi(string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $envVars = $this->environmentService->getProjectEnvVarsWithMeta($project);

        return $this->json([
            'success' => true,
            'variables' => $envVars,
        ]);
    }

    #[Route('/{slug}/environment/create', name: 'app_project_environment_create', methods: ['POST'])]
    public function environmentCreate(string $slug, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $key = $data['key'] ?? '';
        $value = $data['value'] ?? '';
        $isSecret = $data['isSecret'] ?? false;

        if (empty($key) || empty($value)) {
            return $this->json(['success' => false, 'error' => 'Key and value are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->environmentService->createOrUpdate($project, $key, $value, $isSecret);
            return $this->json(['success' => true, 'message' => 'Environment variable created']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Failed to create environment variable'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{slug}/environment/{id}/update', name: 'app_project_environment_update', methods: ['POST'])]
    public function environmentUpdate(string $slug, int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $envVar = $this->envVarRepository->find($id);
        if (!$envVar || $envVar->getProject()->getId() !== $project->getId()) {
            return $this->json(['success' => false, 'error' => 'Environment variable not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $value = $data['value'] ?? '';
        $isSecret = $data['isSecret'] ?? false;

        if (empty($value)) {
            return $this->json(['success' => false, 'error' => 'Value is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->environmentService->createOrUpdate($project, $envVar->getKey(), $value, $isSecret);
            return $this->json(['success' => true, 'message' => 'Environment variable updated']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Failed to update environment variable'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{slug}/environment/{id}/delete', name: 'app_project_environment_delete', methods: ['POST'])]
    public function environmentDelete(string $slug, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $envVar = $this->envVarRepository->find($id);
        if (!$envVar || $envVar->getProject()->getId() !== $project->getId()) {
            return $this->json(['success' => false, 'error' => 'Environment variable not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->environmentService->delete($envVar);
            return $this->json(['success' => true, 'message' => 'Environment variable deleted']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Failed to delete environment variable'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{slug}/environment/export', name: 'app_project_environment_export', methods: ['GET'])]
    public function environmentExport(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $content = $this->environmentService->exportToEnvFile($project);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $project->getSlug() . '.env"');

        return $response;
    }

    #[Route('/{slug}/environment/import', name: 'app_project_environment_import', methods: ['POST'])]
    public function environmentImport(string $slug, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';
        $overwrite = $data['overwrite'] ?? false;

        if (empty($content)) {
            return $this->json(['success' => false, 'error' => 'Content is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->environmentService->importFromEnvFile($project, $content, $overwrite);
            return $this->json([
                'success' => true,
                'imported' => $result['success'],
                'errors' => $result['errors'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Failed to import environment variables'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{slug}/monitoring', name: 'app_project_monitoring', methods: ['GET'])]
    public function monitoring(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        return $this->render('dashboard/projects/monitoring.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/monitoring/stats', name: 'app_project_monitoring_stats', methods: ['GET'])]
    public function monitoringStats(string $slug, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $days = (int) $request->query->get('days', 7);
            $stats = $this->monitoringService->getUptimeStats($project, $days);

            return $this->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{slug}/monitoring/history', name: 'app_project_monitoring_history', methods: ['GET'])]
    public function monitoringHistory(string $slug, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $limit = min((int) $request->query->get('limit', 100), 500);
        $healthChecks = $this->entityManager->getRepository(\App\Entity\HealthCheck::class)
            ->findRecentForProject($project, $limit);

        $data = array_map(function ($check) {
            return [
                'id' => $check->getId(),
                'status' => $check->getStatus(),
                'cpu_usage' => $check->getCpuUsage(),
                'memory_usage' => $check->getMemoryUsage(),
                'response_time' => $check->getResponseTime(),
                'is_running' => $check->isContainerRunning(),
                'checked_at' => $check->getCheckedAt()->format('Y-m-d H:i:s'),
            ];
        }, $healthChecks);

        return $this->json([
            'success' => true,
            'history' => array_reverse($data),
        ]);
    }

    #[Route('/{slug}/monitoring/check', name: 'app_project_monitoring_check', methods: ['POST'])]
    public function monitoringCheck(string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $user]);

        if (!$project) {
            return $this->json(['success' => false, 'error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $healthCheck = $this->monitoringService->performHealthCheck($project);
            $this->alertService->checkAlertRules($project, $healthCheck);

            return $this->json([
                'success' => true,
                'health_check' => [
                    'status' => $healthCheck->getStatus(),
                    'cpu_usage' => $healthCheck->getCpuUsage(),
                    'memory_usage' => $healthCheck->getMemoryUsage(),
                    'response_time' => $healthCheck->getResponseTime(),
                    'is_running' => $healthCheck->isContainerRunning(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Health check failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
