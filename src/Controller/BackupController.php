<?php

namespace App\Controller;

use App\Entity\Backup;
use App\Entity\Database;
use App\Entity\Project;
use App\Message\CreateBackupMessage;
use App\Repository\BackupRepository;
use App\Repository\DatabaseRepository;
use App\Repository\ProjectRepository;
use App\Service\BackupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/projects/{slug}/backups')]
#[IsGranted('ROLE_USER')]
class BackupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BackupRepository $backupRepository,
        private DatabaseRepository $databaseRepository,
        private ProjectRepository $projectRepository,
        private BackupService $backupService,
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * Backup management page
     */
    #[Route('', name: 'app_backup_index', methods: ['GET'])]
    public function index(string $slug): Response
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        return $this->render('dashboard/projects/backups.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Backups list page (HTML) - supports filtering by database
     */
    #[Route('/list', name: 'app_backup_list', methods: ['GET'])]
    public function list(string $slug, Request $request): Response
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $databaseId = $request->query->get('databaseId');
        $database = null;

        if ($databaseId) {
            $database = $this->databaseRepository->find($databaseId);
            if (!$database || $database->getProject() !== $project) {
                throw $this->createNotFoundException('Database not found');
            }
            $backups = $this->backupRepository->findByDatabase($database);
            $stats = $this->backupRepository->getDatabaseStats($database);
        } else {
            $backups = $this->backupRepository->findByProject($project);
            $stats = $this->backupRepository->getProjectStats($project);
        }

        return $this->render('dashboard/backups/list.html.twig', [
            'project' => $project,
            'database' => $database,
            'backups' => $backups,
            'stats' => $stats,
        ]);
    }

    /**
     * Get all backups for a project (API)
     */
    #[Route('/api/list', name: 'app_backup_list_api', methods: ['GET'])]
    public function listApi(string $slug): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $backups = $this->backupRepository->findByProject($project);
        $stats = $this->backupRepository->getProjectStats($project);

        $backupsData = array_map(function (Backup $backup) {
            return [
                'id' => $backup->getId(),
                'name' => $backup->getName(),
                'database_id' => $backup->getDatabase()->getId(),
                'database_name' => $backup->getDatabase()->getName(),
                'type' => $backup->getType(),
                'type_label' => $backup->getTypeLabel(),
                'type_badge_class' => $backup->getTypeBadgeClass(),
                'status' => $backup->getStatus(),
                'status_badge_class' => $backup->getStatusBadgeClass(),
                'method' => $backup->getMethod(),
                'compression' => $backup->getCompression(),
                'file_size' => $backup->getFileSizeFormatted(),
                'file_size_mb' => $backup->getFileSizeMb(),
                'retention_days' => $backup->getRetentionDays(),
                'expires_at' => $backup->getExpiresAt()?->format('Y-m-d H:i:s'),
                'is_expired' => $backup->isExpired(),
                'created_at' => $backup->getCreatedAt()->format('Y-m-d H:i:s'),
                'completed_at' => $backup->getCompletedAt()?->format('Y-m-d H:i:s'),
                'restored_at' => $backup->getRestoredAt()?->format('Y-m-d H:i:s'),
                'created_by' => $backup->getCreatedBy()?->getEmail(),
                'error_message' => $backup->getErrorMessage(),
            ];
        }, $backups);

        return $this->json([
            'success' => true,
            'backups' => $backupsData,
            'stats' => $stats,
        ]);
    }

    /**
     * Get backups for a specific database (API)
     */
    #[Route('/api/database/{databaseId}', name: 'app_backup_list_database_api', methods: ['GET'])]
    public function listByDatabaseApi(string $slug, int $databaseId): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $database = $this->databaseRepository->find($databaseId);

        if (!$database || $database->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Database not found'], 404);
        }

        $backups = $this->backupRepository->findByDatabase($database);
        $stats = $this->backupRepository->getDatabaseStats($database);

        $backupsData = array_map(function (Backup $backup) {
            return [
                'id' => $backup->getId(),
                'name' => $backup->getName(),
                'type' => $backup->getType(),
                'type_label' => $backup->getTypeLabel(),
                'status' => $backup->getStatus(),
                'file_size' => $backup->getFileSizeFormatted(),
                'created_at' => $backup->getCreatedAt()->format('Y-m-d H:i:s'),
                'completed_at' => $backup->getCompletedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $backups);

        return $this->json([
            'success' => true,
            'backups' => $backupsData,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a new backup
     */
    #[Route('/create', name: 'app_backup_create', methods: ['POST'])]
    public function create(string $slug, Request $request): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['database_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'Database ID is required',
            ], 400);
        }

        $database = $this->databaseRepository->find($data['database_id']);

        if (!$database || $database->getProject() !== $project) {
            return $this->json([
                'success' => false,
                'message' => 'Database not found',
            ], 404);
        }

        if (!$database->isRunning()) {
            return $this->json([
                'success' => false,
                'message' => 'Database must be running to create a backup',
            ], 400);
        }

        // Create backup entity
        $backup = new Backup();
        $backup->setDatabase($database);
        $backup->setName($data['name'] ?? 'Backup - ' . date('Y-m-d H:i:s'));
        $backup->setType($data['type'] ?? Backup::TYPE_MANUAL);
        $backup->setMethod($data['method'] ?? Backup::METHOD_DUMP);
        $backup->setCompression($data['compression'] ?? Backup::COMPRESSION_GZIP);
        $backup->setRetentionDays($data['retention_days'] ?? 30);
        $backup->setCreatedBy($this->getUser());

        $this->entityManager->persist($backup);
        $this->entityManager->flush();

        // Dispatch async message to create backup
        $this->messageBus->dispatch(new CreateBackupMessage($backup->getId()));

        return $this->json([
            'success' => true,
            'message' => 'Backup creation has been queued. It will be created shortly.',
            'backup' => [
                'id' => $backup->getId(),
                'name' => $backup->getName(),
                'status' => $backup->getStatus(),
            ],
        ]);
    }

    /**
     * Get single backup details
     */
    #[Route('/{id}', name: 'app_backup_show', methods: ['GET'])]
    public function show(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $backup = $this->backupRepository->find($id);

        if (!$backup || $backup->getDatabase()->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Backup not found'], 404);
        }

        return $this->json([
            'success' => true,
            'backup' => [
                'id' => $backup->getId(),
                'name' => $backup->getName(),
                'database_id' => $backup->getDatabase()->getId(),
                'database_name' => $backup->getDatabase()->getName(),
                'type' => $backup->getType(),
                'type_label' => $backup->getTypeLabel(),
                'status' => $backup->getStatus(),
                'method' => $backup->getMethod(),
                'compression' => $backup->getCompression(),
                'file_path' => $backup->getFilePath(),
                'file_size' => $backup->getFileSizeFormatted(),
                'file_size_bytes' => $backup->getFileSizeBytes(),
                'retention_days' => $backup->getRetentionDays(),
                'expires_at' => $backup->getExpiresAt()?->format('Y-m-d H:i:s'),
                'is_expired' => $backup->isExpired(),
                'created_at' => $backup->getCreatedAt()->format('Y-m-d H:i:s'),
                'completed_at' => $backup->getCompletedAt()?->format('Y-m-d H:i:s'),
                'restored_at' => $backup->getRestoredAt()?->format('Y-m-d H:i:s'),
                'created_by' => $backup->getCreatedBy()?->getEmail(),
                'error_message' => $backup->getErrorMessage(),
                'metadata' => $backup->getMetadata(),
            ],
        ]);
    }

    /**
     * Restore a backup
     */
    #[Route('/{id}/restore', name: 'app_backup_restore', methods: ['POST'])]
    public function restore(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $backup = $this->backupRepository->find($id);

        if (!$backup || $backup->getDatabase()->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Backup not found'], 404);
        }

        if (!$backup->isCompleted()) {
            return $this->json([
                'success' => false,
                'message' => 'Backup is not completed or has failed',
            ], 400);
        }

        $database = $backup->getDatabase();

        if (!$database->isRunning()) {
            return $this->json([
                'success' => false,
                'message' => 'Database must be running to restore a backup',
            ], 400);
        }

        $success = $this->backupService->restoreBackup($backup);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Backup restored successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to restore backup: ' . $backup->getErrorMessage(),
        ], 500);
    }

    /**
     * Download a backup
     */
    #[Route('/{id}/download', name: 'app_backup_download', methods: ['GET'])]
    public function download(string $slug, int $id): Response
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $backup = $this->backupRepository->find($id);

        if (!$backup || $backup->getDatabase()->getProject() !== $project) {
            throw $this->createNotFoundException('Backup not found');
        }

        if (!$backup->isCompleted()) {
            throw $this->createNotFoundException('Backup is not available for download');
        }

        $filePath = $backup->getFilePath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Backup file not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        return $response;
    }

    /**
     * Delete a backup
     */
    #[Route('/{id}', name: 'app_backup_delete', methods: ['DELETE'])]
    public function delete(string $slug, int $id): JsonResponse
    {
        $project = $this->getProjectBySlug($slug);
        $this->checkProjectAccess($project);

        $backup = $this->backupRepository->find($id);

        if (!$backup || $backup->getDatabase()->getProject() !== $project) {
            return $this->json(['success' => false, 'message' => 'Backup not found'], 404);
        }

        $success = $this->backupService->deleteBackup($backup);

        if ($success) {
            return $this->json([
                'success' => true,
                'message' => 'Backup deleted successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Failed to delete backup',
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
