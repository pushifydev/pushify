<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created')]
#[ORM\Index(columns: ['action'], name: 'idx_activity_action')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_activity_entity')]
class ActivityLog
{
    // Action types
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_DEPLOY = 'deploy';
    public const ACTION_ROLLBACK = 'rollback';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_INVITE = 'invite';
    public const ACTION_JOIN = 'join';
    public const ACTION_LEAVE = 'leave';
    public const ACTION_SETTINGS = 'settings';
    public const ACTION_DOMAIN_ADD = 'domain_add';
    public const ACTION_DOMAIN_VERIFY = 'domain_verify';
    public const ACTION_SSL_ISSUE = 'ssl_issue';
    public const ACTION_WEBHOOK_TRIGGER = 'webhook_trigger';
    public const ACTION_SERVER_PROVISION = 'server_provision';
    public const ACTION_SERVER_DELETE = 'server_delete';

    // Entity types
    public const ENTITY_PROJECT = 'project';
    public const ENTITY_DEPLOYMENT = 'deployment';
    public const ENTITY_DOMAIN = 'domain';
    public const ENTITY_SERVER = 'server';
    public const ENTITY_TEAM = 'team';
    public const ENTITY_TEAM_MEMBER = 'team_member';
    public const ENTITY_WEBHOOK = 'webhook';
    public const ENTITY_USER = 'user';
    public const ENTITY_PREVIEW = 'preview';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityName = null;

    #[ORM\Column(length: 500)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
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

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): static
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
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

    // Helper methods

    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => 'Created',
            self::ACTION_UPDATE => 'Updated',
            self::ACTION_DELETE => 'Deleted',
            self::ACTION_DEPLOY => 'Deployed',
            self::ACTION_ROLLBACK => 'Rolled back',
            self::ACTION_LOGIN => 'Logged in',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_INVITE => 'Invited',
            self::ACTION_JOIN => 'Joined',
            self::ACTION_LEAVE => 'Left',
            self::ACTION_SETTINGS => 'Changed settings',
            self::ACTION_DOMAIN_ADD => 'Added domain',
            self::ACTION_DOMAIN_VERIFY => 'Verified domain',
            self::ACTION_SSL_ISSUE => 'Issued SSL',
            self::ACTION_WEBHOOK_TRIGGER => 'Triggered webhook',
            self::ACTION_SERVER_PROVISION => 'Provisioned server',
            self::ACTION_SERVER_DELETE => 'Deleted server',
            default => ucfirst($this->action),
        };
    }

    public function getActionIcon(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => 'plus-circle',
            self::ACTION_UPDATE => 'pencil',
            self::ACTION_DELETE => 'trash',
            self::ACTION_DEPLOY => 'rocket',
            self::ACTION_ROLLBACK => 'arrow-uturn-left',
            self::ACTION_LOGIN => 'arrow-right-on-rectangle',
            self::ACTION_LOGOUT => 'arrow-left-on-rectangle',
            self::ACTION_INVITE => 'envelope',
            self::ACTION_JOIN => 'user-plus',
            self::ACTION_LEAVE => 'user-minus',
            self::ACTION_SETTINGS => 'cog',
            self::ACTION_DOMAIN_ADD => 'globe-alt',
            self::ACTION_DOMAIN_VERIFY => 'check-badge',
            self::ACTION_SSL_ISSUE => 'lock-closed',
            self::ACTION_WEBHOOK_TRIGGER => 'bell',
            self::ACTION_SERVER_PROVISION => 'server',
            self::ACTION_SERVER_DELETE => 'server-stack',
            default => 'information-circle',
        };
    }

    public function getActionColor(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE, self::ACTION_JOIN => 'green',
            self::ACTION_UPDATE, self::ACTION_SETTINGS => 'blue',
            self::ACTION_DELETE, self::ACTION_LEAVE, self::ACTION_SERVER_DELETE => 'red',
            self::ACTION_DEPLOY => 'purple',
            self::ACTION_ROLLBACK => 'orange',
            self::ACTION_LOGIN, self::ACTION_LOGOUT => 'gray',
            self::ACTION_INVITE => 'indigo',
            self::ACTION_DOMAIN_ADD, self::ACTION_DOMAIN_VERIFY => 'cyan',
            self::ACTION_SSL_ISSUE => 'emerald',
            self::ACTION_WEBHOOK_TRIGGER => 'yellow',
            self::ACTION_SERVER_PROVISION => 'violet',
            default => 'gray',
        };
    }

    public function getEntityTypeLabel(): string
    {
        return match ($this->entityType) {
            self::ENTITY_PROJECT => 'Project',
            self::ENTITY_DEPLOYMENT => 'Deployment',
            self::ENTITY_DOMAIN => 'Domain',
            self::ENTITY_SERVER => 'Server',
            self::ENTITY_TEAM => 'Team',
            self::ENTITY_TEAM_MEMBER => 'Team Member',
            self::ENTITY_WEBHOOK => 'Webhook',
            self::ENTITY_USER => 'User',
            self::ENTITY_PREVIEW => 'Preview',
            default => ucfirst($this->entityType ?? ''),
        };
    }

    public function getTimeAgo(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $this->createdAt->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return $this->createdAt->format('M d, Y');
        }
    }
}
