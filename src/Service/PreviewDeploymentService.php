<?php

namespace App\Service;

use App\Entity\PreviewDeployment;
use App\Entity\Project;
use App\Entity\Webhook;
use App\Repository\PreviewDeploymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PreviewDeploymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PreviewDeploymentRepository $previewDeploymentRepository,
        private HttpClientInterface $httpClient,
        private WebhookService $webhookService,
        private LoggerInterface $logger,
        private string $previewDomain = 'preview.pushify.app',
    ) {
    }

    /**
     * Create or update a preview deployment for a PR
     */
    public function createOrUpdatePreview(Project $project, array $prData): PreviewDeployment
    {
        $prNumber = $prData['number'];

        // Check for existing preview
        $preview = $this->previewDeploymentRepository->findByProjectAndPr($project, $prNumber);

        if (!$preview) {
            $preview = new PreviewDeployment();
            $preview->setProject($project);
            $preview->setPrNumber($prNumber);
        }

        // Update PR details
        $preview->setPrTitle($prData['title'] ?? 'Pull Request #' . $prNumber);
        $preview->setBranch($prData['head']['ref'] ?? 'unknown');
        $preview->setCommitHash($prData['head']['sha'] ?? null);
        $preview->setPrAuthor($prData['user']['login'] ?? null);

        // Generate subdomain if not set
        if (!$preview->getSubdomain()) {
            $preview->setSubdomain($preview->generateSubdomain());
            $preview->setPreviewUrl("https://{$preview->getSubdomain()}.{$this->previewDomain}");
        }

        // Set status to pending for new build
        $preview->setStatus(PreviewDeployment::STATUS_PENDING);
        $preview->setErrorMessage(null);

        $this->entityManager->persist($preview);
        $this->entityManager->flush();

        return $preview;
    }

    /**
     * Start building a preview deployment
     */
    public function startBuild(PreviewDeployment $preview): void
    {
        $preview->setStatus(PreviewDeployment::STATUS_BUILDING);
        $preview->setBuildLog('');
        $this->entityManager->flush();

        // Trigger webhook
        $this->triggerWebhook($preview, 'preview.building');
    }

    /**
     * Append to build log
     */
    public function appendLog(PreviewDeployment $preview, string $line): void
    {
        $preview->appendBuildLog($line);
        $this->entityManager->flush();
    }

    /**
     * Mark preview as deploying
     */
    public function startDeploy(PreviewDeployment $preview): void
    {
        $preview->setStatus(PreviewDeployment::STATUS_DEPLOYING);
        $this->entityManager->flush();
    }

    /**
     * Mark preview as active (successfully deployed)
     */
    public function markActive(PreviewDeployment $preview, ?string $containerId = null, ?int $containerPort = null): void
    {
        $preview->setStatus(PreviewDeployment::STATUS_ACTIVE);
        $preview->setDeployedAt(new \DateTimeImmutable());

        if ($containerId) {
            $preview->setContainerId($containerId);
        }
        if ($containerPort) {
            $preview->setContainerPort($containerPort);
        }

        $this->entityManager->flush();

        // Trigger webhook
        $this->triggerWebhook($preview, 'preview.deployed');
    }

    /**
     * Mark preview as failed
     */
    public function markFailed(PreviewDeployment $preview, string $errorMessage): void
    {
        $preview->setStatus(PreviewDeployment::STATUS_FAILED);
        $preview->setErrorMessage($errorMessage);
        $this->entityManager->flush();

        // Trigger webhook
        $this->triggerWebhook($preview, 'preview.failed');
    }

    /**
     * Destroy a preview deployment
     */
    public function destroy(PreviewDeployment $preview): void
    {
        // Stop and remove container if running
        if ($preview->getContainerId()) {
            $this->destroyContainer($preview);
        }

        $preview->setStatus(PreviewDeployment::STATUS_DESTROYED);
        $preview->setDestroyedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Trigger webhook
        $this->triggerWebhook($preview, 'preview.destroyed');
    }

    /**
     * Destroy the Docker container for a preview
     */
    private function destroyContainer(PreviewDeployment $preview): void
    {
        $containerId = $preview->getContainerId();
        if (!$containerId) {
            return;
        }

        try {
            // Stop container
            exec("docker stop {$containerId} 2>&1", $output, $returnCode);

            // Remove container
            exec("docker rm {$containerId} 2>&1", $output, $returnCode);

            $this->logger->info('Preview container destroyed', [
                'containerId' => $containerId,
                'previewId' => $preview->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to destroy preview container', [
                'containerId' => $containerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Post a comment to the GitHub PR with preview URL
     */
    public function postGitHubComment(PreviewDeployment $preview): void
    {
        $project = $preview->getProject();
        $user = $project?->getUser();

        if (!$user || !$user->getGithubAccessToken()) {
            $this->logger->warning('Cannot post GitHub comment: no access token');
            return;
        }

        $repoFullName = $project->getGithubRepo();
        if (!$repoFullName) {
            return;
        }

        $commentBody = $this->buildCommentBody($preview);

        try {
            if ($preview->getGithubCommentId()) {
                // Update existing comment
                $this->updateGitHubComment($user->getGithubAccessToken(), $repoFullName, $preview->getGithubCommentId(), $commentBody);
            } else {
                // Create new comment
                $commentId = $this->createGitHubComment($user->getGithubAccessToken(), $repoFullName, $preview->getPrNumber(), $commentBody);
                $preview->setGithubCommentId($commentId);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to post GitHub comment', [
                'error' => $e->getMessage(),
                'previewId' => $preview->getId(),
            ]);
        }
    }

    /**
     * Build the comment body for GitHub
     */
    private function buildCommentBody(PreviewDeployment $preview): string
    {
        $status = $preview->getStatus();
        $previewUrl = $preview->getPreviewUrl();

        $statusEmoji = match ($status) {
            PreviewDeployment::STATUS_PENDING => '🕐',
            PreviewDeployment::STATUS_BUILDING => '🔨',
            PreviewDeployment::STATUS_DEPLOYING => '🚀',
            PreviewDeployment::STATUS_ACTIVE => '✅',
            PreviewDeployment::STATUS_FAILED => '❌',
            PreviewDeployment::STATUS_DESTROYED => '🗑️',
            default => '❓',
        };

        $statusText = match ($status) {
            PreviewDeployment::STATUS_PENDING => 'Pending',
            PreviewDeployment::STATUS_BUILDING => 'Building...',
            PreviewDeployment::STATUS_DEPLOYING => 'Deploying...',
            PreviewDeployment::STATUS_ACTIVE => 'Ready',
            PreviewDeployment::STATUS_FAILED => 'Failed',
            PreviewDeployment::STATUS_DESTROYED => 'Destroyed',
            default => 'Unknown',
        };

        $lines = [
            "## {$statusEmoji} Preview Deployment",
            '',
            "| Status | {$statusText} |",
            '|--------|--------|',
        ];

        if ($preview->isActive() && $previewUrl) {
            $lines[] = "| **Preview URL** | [{$previewUrl}]({$previewUrl}) |";
        }

        if ($preview->getCommitHash()) {
            $lines[] = "| Commit | `{$preview->getShortCommitHash()}` |";
        }

        if ($preview->getDeployedAt()) {
            $lines[] = "| Deployed | {$preview->getDeployedAt()->format('Y-m-d H:i:s')} UTC |";
        }

        if ($preview->isFailed() && $preview->getErrorMessage()) {
            $lines[] = '';
            $lines[] = '### Error';
            $lines[] = '```';
            $lines[] = $preview->getErrorMessage();
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '*Deployed by [Pushify](https://pushify.app)*';

        return implode("\n", $lines);
    }

    /**
     * Create a GitHub PR comment
     */
    private function createGitHubComment(string $token, string $repoFullName, int $prNumber, string $body): int
    {
        $response = $this->httpClient->request('POST', "https://api.github.com/repos/{$repoFullName}/issues/{$prNumber}/comments", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'json' => [
                'body' => $body,
            ],
        ]);

        $data = $response->toArray();
        return $data['id'];
    }

    /**
     * Update an existing GitHub comment
     */
    private function updateGitHubComment(string $token, string $repoFullName, int $commentId, string $body): void
    {
        $this->httpClient->request('PATCH', "https://api.github.com/repos/{$repoFullName}/issues/comments/{$commentId}", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'json' => [
                'body' => $body,
            ],
        ]);
    }

    /**
     * Trigger webhook for preview events
     */
    private function triggerWebhook(PreviewDeployment $preview, string $event): void
    {
        $project = $preview->getProject();

        $data = [
            'preview' => [
                'id' => $preview->getId(),
                'pr_number' => $preview->getPrNumber(),
                'pr_title' => $preview->getPrTitle(),
                'branch' => $preview->getBranch(),
                'commit_hash' => $preview->getCommitHash(),
                'status' => $preview->getStatus(),
                'preview_url' => $preview->getPreviewUrl(),
                'error_message' => $preview->getErrorMessage(),
            ],
            'project' => $project ? [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'slug' => $project->getSlug(),
            ] : null,
        ];

        $this->webhookService->trigger($event, $data, $project);
    }

    /**
     * Clean up stale previews
     */
    public function cleanupStalePreview(int $days = 7): int
    {
        $stalePreviews = $this->previewDeploymentRepository->findStale($days);
        $count = 0;

        foreach ($stalePreviews as $preview) {
            $this->destroy($preview);
            $count++;
        }

        return $count;
    }

    /**
     * Handle PR closed event - destroy preview
     */
    public function handlePrClosed(Project $project, int $prNumber): void
    {
        $preview = $this->previewDeploymentRepository->findByProjectAndPr($project, $prNumber);

        if ($preview && !$preview->isDestroyed()) {
            $this->destroy($preview);
            $this->postGitHubComment($preview);
        }
    }
}
