<?php

namespace App\Controller;

use App\Entity\Database;
use App\Entity\Project;
use App\Message\CreateDatabaseMessage;
use App\Repository\DatabaseRepository;
use App\Repository\ProjectRepository;
use App\Service\DatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/projects/{slug}/databases')]
#[IsGranted('ROLE_USER')]
class DatabaseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DatabaseRepository $databaseRepository,
        private ProjectRepository $projectRepository,
        private DatabaseService $databaseService,
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * Database management page
     */
    #[Route('', name: 'app_database_index', methods: ['GET'])]
    public function index(string $slug): Response
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        return $this->render('dashboard/projects/databases.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Get all databases for a project
     */
    #[Route('/list', name: 'app_database_list', methods: ['GET'])]
    public function list(string $slug): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $databases = $this->databaseRepository->findByProject($project);
        $stats = $this->databaseRepository->getProjectStats($project);
        $resourceUsage = $this->databaseRepository->getProjectResourceUsage($project);

        $databasesData = array_map(function (Database $db) {
            return [
                'id' => $db->getId(),
                'name' => $db->getName(),
                'type' => $db->getType(),
                'type_label' => $db->getTypeLabel(),
                'type_icon' => $db->getTypeIcon(),
                'status' => $db->getStatus(),
                'status_badge_class' => $db->getStatusBadgeClass(),
                'version' => $db->getVersion(),
                'port' => $db->getPort(),
                'username' => $db->getUsername(),
                'database_name' => $db->getDatabaseName(),
                'connection_string' => $db->getConnectionString(),
                'memory_size_mb' => $db->getMemorySizeMb(),
                'cpu_limit' => $db->getCpuLimit(),
                'uptime' => $db->getUptime(),
                'created_at' => $db->getCreatedAt()->format('Y-m-d H:i:s'),
                'started_at' => $db->getStartedAt()?->format('Y-m-d H:i:s'),
                'error_message' => $db->getErrorMessage(),
            ];
        }, $databases);

        return $this->json([
            'success' => true,
            'databases' => $databasesData,
            'stats' => $stats,
            'resource_usage' => $resourceUsage,
        ]);
    }

    /**
     * Get database types and versions
     */
    #[Route('/types', name: 'app_database_types', methods: ['GET'])]
    public function types(): JsonResponse
    {
        $types = [];
        foreach (Database::getAvailableTypes() as $type => $label) {
            $types[] = [
                'type' => $type,
                'label' => $label,
                'versions' => Database::getAvailableVersions($type),
            ];
        }

        return $this->json([
            'success' => true,
            'types' => $types,
        ]);
    }

    /**
     * Create a new database
     */
    #[Route('/create', name: 'app_database_create', methods: ['POST'])]
    public function create(string $slug, Request $request): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['name']) || empty($data['type'])) {
            return $this->json([
                'success' => false,
                'message' => 'Name and type are required',
            ], 400);
        }

        // Check if database name already exists
        if ($this->databaseRepository->existsByNameAndProject($data['name'], $project)) {
            return $this->json([
                'success' => false,
                'message' => 'A database with this name already exists',
            ], 400);
        }

        // Create database entity
        $database = new Database();
        $database->setProject($project);
        $database->setServer($project->getServer());
        $database->setName($data['name']);
        $database->setType($data['type']);
        $database->setVersion($data['version'] ?? 'latest');
        $database->setUsername($data['username'] ?? 'user_' . bin2hex(random_bytes(4)));
        $database->setPassword($data['password'] ?? bin2hex(random_bytes(16)));
        $database->setDatabaseName($data['database_name'] ?? $data['name']);
        $database->setMemorySizeMb($data['memory_size_mb'] ?? 512);
        $database->setCpuLimit($data['cpu_limit'] ?? 1.0);

        $this->entityManager->persist($database);
        $this->entityManager->flush();

        // Dispatch async message to create Docker container
        $this->messageBus->dispatch(new CreateDatabaseMessage($database->getId()));

        return $this->json([
            'success' => true,
            'message' => 'Database creation has been queued. It will be created shortly.',
            'database' => [
                'id' => $database->getId(),
                'name' => $database->getName(),
                'status' => $database->getStatus(),
            ],
        ]);
    }

    /**
     * Database management page (HTML)
     */
    #[Route('/{id}', name: 'app_database_show', methods: ['GET'])]
    public function show(string $slug, int $id): Response
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            throw $this->createNotFoundException('Database not found');
        }

        return $this->render('dashboard/databases/show.html.twig', [
            'project' => $project,
            'database' => $database,
        ]);
    }

    /**
     * Get single database details (API)
     */
    #[Route('/{id}/api', name: 'app_database_show_api', methods: ['GET'])]
    public function showApi(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        $status = $this->databaseService->getContainerStatus($database);

        return $this->json([
            'success' => true,
            'database' => [
                'id' => $database->getId(),
                'name' => $database->getName(),
                'type' => $database->getType(),
                'type_label' => $database->getTypeLabel(),
                'status' => $database->getStatus(),
                'version' => $database->getVersion(),
                'port' => $database->getPort(),
                'username' => $database->getUsername(),
                'password' => $database->getPassword(),
                'database_name' => $database->getDatabaseName(),
                'connection_string' => $database->getConnectionString(),
                'container_name' => $database->getContainerName(),
                'container_id' => $database->getContainerId(),
                'memory_size_mb' => $database->getMemorySizeMb(),
                'cpu_limit' => $database->getCpuLimit(),
                'uptime' => $database->getUptime(),
                'created_at' => $database->getCreatedAt()->format('Y-m-d H:i:s'),
                'started_at' => $database->getStartedAt()?->format('Y-m-d H:i:s'),
                'error_message' => $database->getErrorMessage(),
                'container_status' => $status,
            ],
        ]);
    }

    /**
     * Start a database
     */
    #[Route('/{id}/start', name: 'app_database_start', methods: ['POST'])]
    public function start(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        if ($database->isRunning()) {
            return $this->json(['success' => false, 'message' => 'Database is already running'], 400);
        }

        $success = $this->databaseService->startDatabase($database);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Database started successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to start database: ' . $database->getErrorMessage(),
        ], 500);
    }

    /**
     * Stop a database
     */
    #[Route('/{id}/stop', name: 'app_database_stop', methods: ['POST'])]
    public function stop(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        if ($database->isStopped()) {
            return $this->json(['success' => false, 'message' => 'Database is already stopped'], 400);
        }

        $success = $this->databaseService->stopDatabase($database);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Database stopped successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to stop database: ' . $database->getErrorMessage(),
        ], 500);
    }

    /**
     * Restart a database
     */
    #[Route('/{id}/restart', name: 'app_database_restart', methods: ['POST'])]
    public function restart(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        $success = $this->databaseService->restartDatabase($database);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Database restarted successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to restart database',
        ], 500);
    }

    /**
     * Update database configuration
     */
    #[Route('/{id}', name: 'app_database_update', methods: ['PUT'])]
    public function update(string $slug, int $id, Request $request): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Update configuration
        if (isset($data['memory_size_mb'])) {
            $database->setMemorySizeMb($data['memory_size_mb']);
        }

        if (isset($data['cpu_limit'])) {
            $database->setCpuLimit($data['cpu_limit']);
        }

        if (isset($data['disk_size_mb'])) {
            $database->setDiskSizeMb($data['disk_size_mb']);
        }

        try {
            $this->entityManager->flush();

            // If database is running, we might need to restart it for changes to take effect
            if ($database->isRunning()) {
                // Note: In a real implementation, you'd want to apply these changes
                // to the running container or schedule a restart
            }

            return $this->json([
                'success' => true,
                'message' => 'Database configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to update database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fix remote access for existing database
     */
    #[Route('/{id}/fix-remote-access', name: 'app_database_fix_remote_access', methods: ['POST'])]
    public function fixRemoteAccess(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        if (!$database->isRunning()) {
            return $this->json([
                'success' => false,
                'message' => 'Database must be running to fix remote access',
            ], 400);
        }

        try {
            $success = $this->databaseService->fixRemoteAccess($database);

            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Remote access configured successfully',
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => 'Failed to configure remote access',
            ], 500);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a database
     */
    #[Route('/{id}', name: 'app_database_delete', methods: ['DELETE'])]
    public function delete(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($id);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        $success = $this->databaseService->deleteDatabase($database);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Database deleted successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to delete database: ' . $database->getErrorMessage(),
        ], 500);
    }

    /**
     * Get project by slug
     */
    private function getProjectBySlug(string $slug): Project
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        return $project;
    }

    /**
     * Check if user is a member of the project's team
     */
    private function isTeamMember(Project $project, $user): bool
    {
        $team = $project->getTeam();
        if (!$team) {
            return false;
        }

        foreach ($team->getMembers() as $member) {
            if ($member->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has access to the project
     */
    private function checkProjectAccess(Project $project): void
    {
        $user = $this->getUser();
        if (!$user || ($project->getOwner() !== $user && !$this->isTeamMember($project, $user))) {
            throw $this->createAccessDeniedException('You do not have access to this project.');
        }
    }
}
