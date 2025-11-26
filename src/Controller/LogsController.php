<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\LogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/projects/{slug}/logs')]
#[IsGranted('ROLE_USER')]
class LogsController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private LogService $logService
    ) {
    }

    #[Route('', name: 'app_project_logs', methods: ['GET'])]
    public function index(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        // Don't block page load with SSH - pass basic info and fetch status via JS
        $containerStatus = [
            'status' => 'loading',
            'running' => false,
            'stats' => null,
        ];

        // Only try to get status if we have server and container info
        $server = $project->getServer();
        $containerId = $project->getContainerId();

        if ($server && $server->isActive() && $containerId) {
            try {
                $containerStatus = $this->logService->getContainerStatus($project);
            } catch (\Exception $e) {
                $containerStatus = [
                    'status' => 'error',
                    'running' => false,
                    'error' => $e->getMessage(),
                ];
            }
        } elseif (!$server) {
            $containerStatus = [
                'status' => 'no_server',
                'running' => false,
                'error' => 'No server assigned',
            ];
        } elseif (!$containerId) {
            $containerStatus = [
                'status' => 'not_deployed',
                'running' => false,
                'error' => 'Project not deployed yet',
            ];
        }

        return $this->render('dashboard/logs/project.html.twig', [
            'project' => $project,
            'containerStatus' => $containerStatus,
        ]);
    }

    #[Route('/fetch', name: 'app_project_logs_fetch', methods: ['GET'])]
    public function fetch(Request $request, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new JsonResponse(['success' => false, 'error' => 'Project not found', 'logs' => ''], 404);
        }

        // Debug: Check project details
        $server = $project->getServer();
        $containerId = $project->getContainerId();

        if (!$server) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No server assigned to project',
                'logs' => '',
                'debug' => ['projectId' => $project->getId(), 'slug' => $slug]
            ]);
        }

        if (!$containerId) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No container ID found - project may not be deployed yet',
                'logs' => '',
                'debug' => ['projectId' => $project->getId(), 'serverId' => $server->getId()]
            ]);
        }

        $lines = min((int) $request->query->get('lines', 100), 1000);

        $result = $this->logService->getContainerLogs($project, $lines);

        return new JsonResponse($result);
    }

    #[Route('/status', name: 'app_project_logs_status', methods: ['GET'])]
    public function status(string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $result = $this->logService->getContainerStatus($project);

        return new JsonResponse($result);
    }

    #[Route('/restart', name: 'app_project_logs_restart', methods: ['POST'])]
    public function restart(Request $request, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $result = $this->logService->restartContainer($project);

        return new JsonResponse($result);
    }

    #[Route('/stop', name: 'app_project_logs_stop', methods: ['POST'])]
    public function stop(Request $request, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $result = $this->logService->stopContainer($project);

        return new JsonResponse($result);
    }

    #[Route('/start', name: 'app_project_logs_start', methods: ['POST'])]
    public function start(Request $request, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $result = $this->logService->startContainer($project);

        return new JsonResponse($result);
    }

    #[Route('/stream', name: 'app_project_logs_stream', methods: ['GET'])]
    public function streamLogs(Request $request, string $slug): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return new StreamedResponse(function () {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Project not found']) . "\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $server = $project->getServer();
        $containerId = $project->getContainerId();

        if (!$server || !$server->isActive()) {
            return new StreamedResponse(function () {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'No active server assigned']) . "\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        if (!$containerId) {
            return new StreamedResponse(function () {
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'No container deployed']) . "\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $lines = min((int) $request->query->get('lines', 100), 1000);
        $logService = $this->logService;

        $response = new StreamedResponse(function () use ($project, $logService, $lines) {
            // Disable ALL output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Disable implicit flush
            ob_implicit_flush(true);

            // Set unlimited execution time for streaming
            set_time_limit(0);

            // Send initial connection event
            echo "event: connected\n";
            echo "data: " . json_encode(['message' => 'Connected to log stream']) . "\n\n";
            @ob_flush();
            @flush();

            // Stream logs
            $logService->streamContainerLogs($project, $lines, function ($line) {
                echo "event: log\n";
                echo "data: " . json_encode(['line' => $line]) . "\n\n";
                @ob_flush();
                @flush();

                // Check if client disconnected
                if (connection_aborted()) {
                    return false;
                }
                return true;
            });

            // Send end event
            echo "event: end\n";
            echo "data: " . json_encode(['message' => 'Stream ended']) . "\n\n";
            @ob_flush();
            @flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}
