<?php

namespace App\Entity;

use App\Repository\DeploymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeploymentRepository::class)]
#[ORM\Table(name: 'deployments')]
#[ORM\Index(columns: ['status'], name: 'idx_deployment_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_deployment_created')]
class Deployment
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_BUILDING = 'building';
    public const STATUS_DEPLOYING = 'deploying';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_GIT_PUSH = 'git_push';
    public const TRIGGER_ROLLBACK = 'rollback';
    public const TRIGGER_REDEPLOY = 'redeploy';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $triggeredBy = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_QUEUED;

    #[ORM\Column(length: 20)]
    private ?string $trigger = self::TRIGGER_MANUAL;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $commitHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commitMessage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $branch = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dockerImage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dockerTag = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $buildLogs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deployLogs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?int $buildDuration = null; // in seconds

    #[ORM\Column(nullable: true)]
    private ?int $deployDuration = null; // in seconds

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deploymentUrl = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $buildStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $buildFinishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deployStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deployFinishedAt = null;

    #[ORM\ManyToOne(targetEntity: Deployment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Deployment $rollbackFrom = null;

    #[ORM\Column]
    private bool $isCurrentProduction = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTriggeredBy(): ?User
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?User $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTrigger(): ?string
    {
        return $this->trigger;
    }

    public function setTrigger(string $trigger): static
    {
        $this->trigger = $trigger;
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

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = $branch;
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

    public function getBuildLogs(): ?string
    {
        return $this->buildLogs;
    }

    public function setBuildLogs(?string $buildLogs): static
    {
        $this->buildLogs = $buildLogs;
        return $this;
    }

    public function appendBuildLog(string $log): static
    {
        $this->buildLogs = ($this->buildLogs ?? '') . $log . "\n";
        return $this;
    }

    public function getDeployLogs(): ?string
    {
        return $this->deployLogs;
    }

    public function setDeployLogs(?string $deployLogs): static
    {
        $this->deployLogs = $deployLogs;
        return $this;
    }

    public function appendDeployLog(string $log): static
    {
        $this->deployLogs = ($this->deployLogs ?? '') . $log . "\n";
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

    public function getBuildDuration(): ?int
    {
        return $this->buildDuration;
    }

    public function setBuildDuration(?int $buildDuration): static
    {
        $this->buildDuration = $buildDuration;
        return $this;
    }

    public function getDeployDuration(): ?int
    {
        return $this->deployDuration;
    }

    public function setDeployDuration(?int $deployDuration): static
    {
        $this->deployDuration = $deployDuration;
        return $this;
    }

    public function getTotalDuration(): ?int
    {
        if ($this->buildDuration === null && $this->deployDuration === null) {
            return null;
        }
        return ($this->buildDuration ?? 0) + ($this->deployDuration ?? 0);
    }

    public function getDeploymentUrl(): ?string
    {
        return $this->deploymentUrl;
    }

    public function setDeploymentUrl(?string $deploymentUrl): static
    {
        $this->deploymentUrl = $deploymentUrl;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getBuildStartedAt(): ?\DateTimeImmutable
    {
        return $this->buildStartedAt;
    }

    public function setBuildStartedAt(?\DateTimeImmutable $buildStartedAt): static
    {
        $this->buildStartedAt = $buildStartedAt;
        return $this;
    }

    public function getBuildFinishedAt(): ?\DateTimeImmutable
    {
        return $this->buildFinishedAt;
    }

    public function setBuildFinishedAt(?\DateTimeImmutable $buildFinishedAt): static
    {
        $this->buildFinishedAt = $buildFinishedAt;
        return $this;
    }

    public function getDeployStartedAt(): ?\DateTimeImmutable
    {
        return $this->deployStartedAt;
    }

    public function setDeployStartedAt(?\DateTimeImmutable $deployStartedAt): static
    {
        $this->deployStartedAt = $deployStartedAt;
        return $this;
    }

    public function getDeployFinishedAt(): ?\DateTimeImmutable
    {
        return $this->deployFinishedAt;
    }

    public function setDeployFinishedAt(?\DateTimeImmutable $deployFinishedAt): static
    {
        $this->deployFinishedAt = $deployFinishedAt;
        return $this;
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isBuilding(): bool
    {
        return $this->status === self::STATUS_BUILDING;
    }

    public function isDeploying(): bool
    {
        return $this->status === self::STATUS_DEPLOYING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRunning(): bool
    {
        return \in_array($this->status, [self::STATUS_QUEUED, self::STATUS_BUILDING, self::STATUS_DEPLOYING], true);
    }

    public function isFinished(): bool
    {
        return \in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED], true);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'bg-green-500/20 text-green-400',
            self::STATUS_BUILDING, self::STATUS_DEPLOYING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_QUEUED => 'bg-blue-500/20 text-blue-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            self::STATUS_CANCELLED => 'bg-gray-500/20 text-gray-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'check-circle',
            self::STATUS_BUILDING, self::STATUS_DEPLOYING => 'refresh',
            self::STATUS_QUEUED => 'clock',
            self::STATUS_FAILED => 'x-circle',
            self::STATUS_CANCELLED => 'ban',
            default => 'question-mark-circle',
        };
    }

    public function getRollbackFrom(): ?Deployment
    {
        return $this->rollbackFrom;
    }

    public function setRollbackFrom(?Deployment $rollbackFrom): static
    {
        $this->rollbackFrom = $rollbackFrom;
        return $this;
    }

    public function isRollback(): bool
    {
        return $this->trigger === self::TRIGGER_ROLLBACK;
    }

    public function isCurrentProduction(): bool
    {
        return $this->isCurrentProduction;
    }

    public function setIsCurrentProduction(bool $isCurrentProduction): static
    {
        $this->isCurrentProduction = $isCurrentProduction;
        return $this;
    }

    /**
     * Check if this deployment can be rolled back to
     */
    public function canRollbackTo(): bool
    {
        return $this->isSuccess() && $this->dockerImage !== null;
    }

    public function getTriggerLabel(): string
    {
        return match ($this->trigger) {
            self::TRIGGER_MANUAL => 'Manual',
            self::TRIGGER_GIT_PUSH => 'Git Push',
            self::TRIGGER_ROLLBACK => 'Rollback',
            self::TRIGGER_REDEPLOY => 'Redeploy',
            default => 'Unknown',
        };
    }

    public function getTriggerBadgeClass(): string
    {
        return match ($this->trigger) {
            self::TRIGGER_MANUAL => 'bg-blue-500/20 text-blue-400',
            self::TRIGGER_GIT_PUSH => 'bg-purple-500/20 text-purple-400',
            self::TRIGGER_ROLLBACK => 'bg-orange-500/20 text-orange-400',
            self::TRIGGER_REDEPLOY => 'bg-cyan-500/20 text-cyan-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }
}
