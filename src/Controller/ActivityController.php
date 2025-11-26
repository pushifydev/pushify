<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\ProjectRepository;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/activity')]
#[IsGranted('ROLE_USER')]
class ActivityController extends AbstractController
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private ActivityLogService $activityLogService,
        private ProjectRepository $projectRepository
    ) {
    }

    #[Route('', name: 'app_activity')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $activities = $this->activityLogRepository->findVisibleToUser($user, $limit, $offset);

        // Get timeline for sidebar widget
        $timeline = $this->activityLogService->getTimeline($user, 7);

        // Get stats
        $since = new \DateTimeImmutable('-30 days');
        $stats = $this->activityLogRepository->getStatsForUser($user, $since);

        return $this->render('dashboard/activity/index.html.twig', [
            'activities' => $activities,
            'timeline' => $timeline,
            'stats' => $stats,
            'page' => $page,
            'hasMore' => count($activities) === $limit,
        ]);
    }

    #[Route('/project/{slug}', name: 'app_activity_project')]
    public function projectActivity(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $activities = $this->activityLogService->getForProject($project, 100);

        return $this->render('dashboard/activity/project.html.twig', [
            'project' => $project,
            'activities' => $activities,
        ]);
    }

    #[Route('/api/recent', name: 'app_activity_api_recent')]
    public function apiRecent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = min(50, $request->query->getInt('limit', 10));
        $activities = $this->activityLogRepository->findVisibleToUser($user, $limit);

        $data = array_map(function ($activity) {
            return [
                'id' => $activity->getId(),
                'action' => $activity->getAction(),
                'actionLabel' => $activity->getActionLabel(),
                'description' => $activity->getDescription(),
                'entityType' => $activity->getEntityType(),
                'entityName' => $activity->getEntityName(),
                'projectName' => $activity->getProject()?->getName(),
                'userName' => $activity->getUser()?->getEmail(),
                'timeAgo' => $activity->getTimeAgo(),
                'createdAt' => $activity->getCreatedAt()->format('c'),
                'icon' => $activity->getActionIcon(),
                'color' => $activity->getActionColor(),
            ];
        }, $activities);

        return $this->json(['activities' => $data]);
    }

    #[Route('/api/stats', name: 'app_activity_api_stats')]
    public function apiStats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $since = new \DateTimeImmutable('-30 days');
        $stats = $this->activityLogRepository->getStatsForUser($user, $since);

        $total = array_sum($stats);
        $deployments = ($stats['deploy'] ?? 0);
        $creates = ($stats['create'] ?? 0);
        $updates = ($stats['update'] ?? 0);

        return $this->json([
            'total' => $total,
            'deployments' => $deployments,
            'creates' => $creates,
            'updates' => $updates,
            'breakdown' => $stats,
        ]);
    }
}
