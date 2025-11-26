<?php

namespace App\Entity;

use App\Repository\WebhookRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookRepository::class)]
#[ORM\Table(name: 'webhooks')]
#[ORM\HasLifecycleCallbacks]
class Webhook
{
    // Event types
    public const EVENT_DEPLOYMENT_STARTED = 'deployment.started';
    public const EVENT_DEPLOYMENT_SUCCESS = 'deployment.success';
    public const EVENT_DEPLOYMENT_FAILED = 'deployment.failed';
    public const EVENT_DEPLOYMENT_CANCELLED = 'deployment.cancelled';
    public const EVENT_DOMAIN_ADDED = 'domain.added';
    public const EVENT_DOMAIN_VERIFIED = 'domain.verified';
    public const EVENT_SSL_ISSUED = 'ssl.issued';
    public const EVENT_SSL_EXPIRING = 'ssl.expiring';

    // Preset types
    public const PRESET_CUSTOM = 'custom';
    public const PRESET_SLACK = 'slack';
    public const PRESET_DISCORD = 'discord';
    public const PRESET_TEAMS = 'teams';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 50)]
    private string $preset = self::PRESET_CUSTOM;

    #[ORM\Column(type: Types::JSON)]
    private array $events = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $headers = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastTriggeredAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastResponseCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private int $successCount = 0;

    #[ORM\Column]
    private int $failureCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getPreset(): string
    {
        return $this->preset;
    }

    public function setPreset(string $preset): static
    {
        $this->preset = $preset;
        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function setEvents(array $events): static
    {
        $this->events = $events;
        return $this;
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;
        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastTriggeredAt(): ?\DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function setLastTriggeredAt(?\DateTimeImmutable $lastTriggeredAt): static
    {
        $this->lastTriggeredAt = $lastTriggeredAt;
        return $this;
    }

    public function getLastResponseCode(): ?int
    {
        return $this->lastResponseCode;
    }

    public function setLastResponseCode(?int $lastResponseCode): static
    {
        $this->lastResponseCode = $lastResponseCode;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function incrementSuccessCount(): static
    {
        $this->successCount++;
        return $this;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function incrementFailureCount(): static
    {
        $this->failureCount++;
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

    // Helper methods
    public function isSlack(): bool
    {
        return $this->preset === self::PRESET_SLACK;
    }

    public function isDiscord(): bool
    {
        return $this->preset === self::PRESET_DISCORD;
    }

    public function isTeams(): bool
    {
        return $this->preset === self::PRESET_TEAMS;
    }

    public function isCustom(): bool
    {
        return $this->preset === self::PRESET_CUSTOM;
    }

    public function getPresetLabel(): string
    {
        return match ($this->preset) {
            self::PRESET_SLACK => 'Slack',
            self::PRESET_DISCORD => 'Discord',
            self::PRESET_TEAMS => 'Microsoft Teams',
            default => 'Custom Webhook',
        };
    }

    public function getStatusBadgeClass(): string
    {
        if (!$this->isActive) {
            return 'bg-gray-500/20 text-gray-400';
        }

        if ($this->lastResponseCode === null) {
            return 'bg-blue-500/20 text-blue-400';
        }

        if ($this->lastResponseCode >= 200 && $this->lastResponseCode < 300) {
            return 'bg-green-500/20 text-green-400';
        }

        return 'bg-red-500/20 text-red-400';
    }

    public function getStatusLabel(): string
    {
        if (!$this->isActive) {
            return 'Disabled';
        }

        if ($this->lastResponseCode === null) {
            return 'Never triggered';
        }

        if ($this->lastResponseCode >= 200 && $this->lastResponseCode < 300) {
            return 'Healthy';
        }

        return 'Failing';
    }

    public static function getAllEvents(): array
    {
        return [
            self::EVENT_DEPLOYMENT_STARTED => 'Deployment Started',
            self::EVENT_DEPLOYMENT_SUCCESS => 'Deployment Succeeded',
            self::EVENT_DEPLOYMENT_FAILED => 'Deployment Failed',
            self::EVENT_DEPLOYMENT_CANCELLED => 'Deployment Cancelled',
            self::EVENT_DOMAIN_ADDED => 'Domain Added',
            self::EVENT_DOMAIN_VERIFIED => 'Domain Verified',
            self::EVENT_SSL_ISSUED => 'SSL Certificate Issued',
            self::EVENT_SSL_EXPIRING => 'SSL Certificate Expiring',
        ];
    }

    public static function getDeploymentEvents(): array
    {
        return [
            self::EVENT_DEPLOYMENT_STARTED,
            self::EVENT_DEPLOYMENT_SUCCESS,
            self::EVENT_DEPLOYMENT_FAILED,
            self::EVENT_DEPLOYMENT_CANCELLED,
        ];
    }
}
