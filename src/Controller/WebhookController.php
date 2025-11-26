<?php

namespace App\Controller;

use App\Entity\Deployment;
use App\Repository\ProjectRepository;
use App\Service\DeploymentService;
use App\Service\PreviewDeploymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private DeploymentService $deploymentService,
        private PreviewDeploymentService $previewDeploymentService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/github/{webhookSecret}', name: 'app_webhook_github', methods: ['POST'])]
    public function github(Request $request, string $webhookSecret): JsonResponse
    {
        // Find project by webhook secret
        $project = $this->projectRepository->findByWebhookSecret($webhookSecret);

        if (!$project) {
            $this->logger->warning('Webhook received for unknown secret', [
                'secret' => substr($webhookSecret, 0, 8) . '...',
            ]);
            return $this->json(['error' => 'Invalid webhook'], Response::HTTP_NOT_FOUND);
        }

        // Verify GitHub signature if configured
        $signature = $request->headers->get('X-Hub-Signature-256');
        if ($signature && $project->getGithubWebhookSecret()) {
            $payload = $request->getContent();
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $project->getGithubWebhookSecret());

            if (!hash_equals($expectedSignature, $signature)) {
                $this->logger->warning('Invalid webhook signature', [
                    'project' => $project->getSlug(),
                ]);
                return $this->json(['error' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
            }
        }

        // Parse event type
        $event = $request->headers->get('X-GitHub-Event');
        $payload = json_decode($request->getContent(), true);

        $this->logger->info('GitHub webhook received', [
            'project' => $project->getSlug(),
            'event' => $event,
        ]);

        // Handle push event
        if ($event === 'push') {
            return $this->handlePushEvent($project, $payload);
        }

        // Handle pull_request event for preview deployments
        if ($event === 'pull_request') {
            return $this->handlePullRequestEvent($project, $payload);
        }

        // Handle ping event (sent when webhook is first created)
        if ($event === 'ping') {
            return $this->json([
                'message' => 'Webhook configured successfully',
                'project' => $project->getName(),
            ]);
        }

        return $this->json(['message' => 'Event ignored']);
    }

    private function handlePullRequestEvent($project, array $payload): JsonResponse
    {
        // Check if preview deployments are enabled
        if (!$project->isPreviewDeploymentsEnabled()) {
            return $this->json(['message' => 'Preview deployments are disabled']);
        }

        $action = $payload['action'] ?? '';
        $prData = $payload['pull_request'] ?? [];
        $prNumber = $prData['number'] ?? 0;

        if (!$prNumber) {
            return $this->json(['error' => 'Invalid PR data'], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Pull request event received', [
            'project' => $project->getSlug(),
            'action' => $action,
            'pr_number' => $prNumber,
        ]);

        try {
            // Handle PR opened or synchronized (new commits pushed)
            if (in_array($action, ['opened', 'synchronize', 'reopened'])) {
                $preview = $this->previewDeploymentService->createOrUpdatePreview($project, $prData);

                // Post initial comment to PR
                $this->previewDeploymentService->postGitHubComment($preview);

                // TODO: Queue the actual build/deploy process
                // For now, we just create the preview record
                // In a real implementation, you'd dispatch a message to a queue

                return $this->json([
                    'message' => 'Preview deployment queued',
                    'preview_id' => $preview->getId(),
                    'pr_number' => $prNumber,
                    'preview_url' => $preview->getPreviewUrl(),
                ]);
            }

            // Handle PR closed or merged
            if ($action === 'closed') {
                $this->previewDeploymentService->handlePrClosed($project, $prNumber);

                return $this->json([
                    'message' => 'Preview deployment destroyed',
                    'pr_number' => $prNumber,
                ]);
            }

            return $this->json(['message' => 'PR action ignored', 'action' => $action]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle pull request event', [
                'project' => $project->getSlug(),
                'pr_number' => $prNumber,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Failed to process pull request event',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handlePushEvent($project, array $payload): JsonResponse
    {
        // Check if auto-deploy is enabled
        if (!$project->isAutoDeployEnabled()) {
            return $this->json(['message' => 'Auto-deploy is disabled']);
        }

        // Get the branch from the push event
        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);

        // Only deploy if push is to the production branch
        if ($branch !== $project->getBranch()) {
            return $this->json([
                'message' => 'Push to non-production branch ignored',
                'pushed_branch' => $branch,
                'production_branch' => $project->getBranch(),
            ]);
        }

        // Get commit info
        $commits = $payload['commits'] ?? [];
        $headCommit = $payload['head_commit'] ?? null;
        $commitHash = $headCommit['id'] ?? $payload['after'] ?? null;
        $commitMessage = $headCommit['message'] ?? 'Push event';
        $pusher = $payload['pusher']['name'] ?? 'GitHub';

        // Don't deploy if no commits (e.g., branch deletion)
        if (empty($commits) && !$commitHash) {
            return $this->json(['message' => 'No commits to deploy']);
        }

        // Create deployment
        try {
            $deployment = $this->deploymentService->createDeployment(
                $project,
                $project->getOwner(),
                Deployment::TRIGGER_GIT_PUSH,
                $commitHash,
                $commitMessage
            );

            $this->logger->info('Auto-deployment triggered', [
                'project' => $project->getSlug(),
                'deployment_id' => $deployment->getId(),
                'branch' => $branch,
                'commit' => substr($commitHash ?? '', 0, 7),
                'pusher' => $pusher,
            ]);

            return $this->json([
                'message' => 'Deployment triggered',
                'deployment_id' => $deployment->getId(),
                'commit' => substr($commitHash ?? '', 0, 7),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create deployment from webhook', [
                'project' => $project->getSlug(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => 'Failed to create deployment',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
