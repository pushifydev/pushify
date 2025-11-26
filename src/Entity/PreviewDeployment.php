<?php

namespace App\Entity;

use App\Repository\PreviewDeploymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreviewDeploymentRepository::class)]
#[ORM\Table(name: 'preview_deployments')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['project_id', 'pr_number'], name: 'idx_preview_project_pr')]
class PreviewDeployment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_BUILDING = 'building';
    public const STATUS_DEPLOYING = 'deploying';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DESTROYED = 'destroyed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column]
    private int $prNumber;

    #[ORM\Column(length: 255)]
    private string $prTitle;

    #[ORM\Column(length: 100)]
    private string $branch;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $commitHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commitMessage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prAuthor = null;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $subdomain = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dockerImage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dockerTag = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $containerId = null;

    #[ORM\Column(nullable: true)]
    private ?int $containerPort = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $buildLog = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?int $githubCommentId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deployedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $destroyedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getPrNumber(): int
    {
        return $this->prNumber;
    }

    public function setPrNumber(int $prNumber): static
    {
        $this->prNumber = $prNumber;
        return $this;
    }

    public function getPrTitle(): string
    {
        return $this->prTitle;
    }

    public function setPrTitle(string $prTitle): static
    {
        $this->prTitle = $prTitle;
        return $this;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function setBranch(string $branch): static
    {
        $this->branch = $branch;
        return $this;
    }

    public function getCommitHash(): ?string
    {
        return $this->commitHash;
    }

    public function setCommitHash(?string $commitHash): static
    {
        $this->commitHash = $commitHash;
        return $this;
    }

    public function getShortCommitHash(): ?string
    {
        return $this->commitHash ? substr($this->commitHash, 0, 7) : null;
    }

    public function getCommitMessage(): ?string
    {
        return $this->commitMessage;
    }

    public function setCommitMessage(?string $commitMessage): static
    {
        $this->commitMessage = $commitMessage;
        return $this;
    }

    public function getPrAuthor(): ?string
    {
        return $this->prAuthor;
    }

    public function setPrAuthor(?string $prAuthor): static
    {
        $this->prAuthor = $prAuthor;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPreviewUrl(): ?string
    {
        return $this->previewUrl;
    }

    public function setPreviewUrl(?string $previewUrl): static
    {
        $this->previewUrl = $previewUrl;
        return $this;
    }

    public function getSubdomain(): ?string
    {
        return $this->subdomain;
    }

    public function setSubdomain(?string $subdomain): static
    {
        $this->subdomain = $subdomain;
        return $this;
    }

    public function getDockerImage(): ?string
    {
        return $this->dockerImage;
    }

    public function setDockerImage(?string $dockerImage): static
    {
        $this->dockerImage = $dockerImage;
        return $this;
    }

    public function getDockerTag(): ?string
    {
        return $this->dockerTag;
    }

    public function setDockerTag(?string $dockerTag): static
    {
        $this->dockerTag = $dockerTag;
        return $this;
    }

    public function getContainerId(): ?string
    {
        return $this->containerId;
    }

    public function setContainerId(?string $containerId): static
    {
        $this->containerId = $containerId;
        return $this;
    }

    public function getContainerPort(): ?int
    {
        return $this->containerPort;
    }

    public function setContainerPort(?int $containerPort): static
    {
        $this->containerPort = $containerPort;
        return $this;
    }

    public function getBuildLog(): ?string
    {
        return $this->buildLog;
    }

    public function setBuildLog(?string $buildLog): static
    {
        $this->buildLog = $buildLog;
        return $this;
    }

    public function appendBuildLog(string $line): static
    {
        $this->buildLog = ($this->buildLog ?? '') . $line . "\n";
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getGithubCommentId(): ?int
    {
        return $this->githubCommentId;
    }

    public function setGithubCommentId(?int $githubCommentId): static
    {
        $this->githubCommentId = $githubCommentId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeployedAt(): ?\DateTimeImmutable
    {
        return $this->deployedAt;
    }

    public function setDeployedAt(?\DateTimeImmutable $deployedAt): static
    {
        $this->deployedAt = $deployedAt;
        return $this;
    }

    public function getDestroyedAt(): ?\DateTimeImmutable
    {
        return $this->destroyedAt;
    }

    public function setDestroyedAt(?\DateTimeImmutable $destroyedAt): static
    {
        $this->destroyedAt = $destroyedAt;
        return $this;
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isBuilding(): bool
    {
        return $this->status === self::STATUS_BUILDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDestroyed(): bool
    {
        return $this->status === self::STATUS_DESTROYED;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_BUILDING, self::STATUS_DEPLOYING], true);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-green-500/20 text-green-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            self::STATUS_BUILDING, self::STATUS_DEPLOYING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_DESTROYED => 'bg-gray-500/20 text-gray-400',
            default => 'bg-blue-500/20 text-blue-400',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_BUILDING => 'Building',
            self::STATUS_DEPLOYING => 'Deploying',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_DESTROYED => 'Destroyed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Generate subdomain for preview
     */
    public function generateSubdomain(): string
    {
        $projectSlug = $this->project?->getSlug() ?? 'preview';
        return "pr-{$this->prNumber}-{$projectSlug}";
    }
}
