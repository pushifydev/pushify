<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['user_id', 'read_at'], name: 'idx_notification_user_read')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    // Notification types
    public const TYPE_DEPLOYMENT_STARTED = 'deployment_started';
    public const TYPE_DEPLOYMENT_SUCCESS = 'deployment_success';
    public const TYPE_DEPLOYMENT_FAILED = 'deployment_failed';
    public const TYPE_SERVER_OFFLINE = 'server_offline';
    public const TYPE_SERVER_ONLINE = 'server_online';
    public const TYPE_DOMAIN_SSL_EXPIRING = 'domain_ssl_expiring';
    public const TYPE_DOMAIN_SSL_RENEWED = 'domain_ssl_renewed';
    public const TYPE_SYSTEM = 'system';

    // Channels
    public const CHANNEL_APP = 'app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WEBHOOK = 'webhook';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $actionLabel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): static
    {
        $this->actionUrl = $actionUrl;
        return $this;
    }

    public function getActionLabel(): ?string
    {
        return $this->actionLabel;
    }

    public function setActionLabel(?string $actionLabel): static
    {
        $this->actionLabel = $actionLabel;
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markAsRead(): static
    {
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    public function getEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->emailSentAt;
    }

    public function setEmailSentAt(?\DateTimeImmutable $emailSentAt): static
    {
        $this->emailSentAt = $emailSentAt;
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

    // Helper methods for type icons/colors
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_DEPLOYMENT_STARTED => 'rocket',
            self::TYPE_DEPLOYMENT_SUCCESS => 'check-circle',
            self::TYPE_DEPLOYMENT_FAILED => 'x-circle',
            self::TYPE_SERVER_OFFLINE => 'server-off',
            self::TYPE_SERVER_ONLINE => 'server',
            self::TYPE_DOMAIN_SSL_EXPIRING => 'shield-alert',
            self::TYPE_DOMAIN_SSL_RENEWED => 'shield-check',
            default => 'bell',
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_DEPLOYMENT_STARTED => 'blue',
            self::TYPE_DEPLOYMENT_SUCCESS => 'green',
            self::TYPE_DEPLOYMENT_FAILED => 'red',
            self::TYPE_SERVER_OFFLINE => 'red',
            self::TYPE_SERVER_ONLINE => 'green',
            self::TYPE_DOMAIN_SSL_EXPIRING => 'yellow',
            self::TYPE_DOMAIN_SSL_RENEWED => 'green',
            default => 'gray',
        };
    }

    public function getTypeBadgeClass(): string
    {
        $color = $this->getTypeColor();
        return match ($color) {
            'green' => 'bg-green-500/20 text-green-400',
            'red' => 'bg-red-500/20 text-red-400',
            'yellow' => 'bg-yellow-500/20 text-yellow-400',
            'blue' => 'bg-blue-500/20 text-blue-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getTimeAgo(): string
    {
        $diff = $this->createdAt->diff(new \DateTimeImmutable());

        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        }
        if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        }
        if ($diff->d > 0) {
            return $diff->d . 'd ago';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ago';
        }
        if ($diff->i > 0) {
            return $diff->i . 'm ago';
        }
        return 'just now';
    }
}
