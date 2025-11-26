<?php

namespace App\Entity;

use App\Repository\HealthCheckRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HealthCheckRepository::class)]
#[ORM\Table(name: 'health_checks')]
#[ORM\Index(columns: ['project_id', 'checked_at'], name: 'idx_health_project_time')]
#[ORM\Index(columns: ['status'], name: 'idx_health_status')]
class HealthCheck
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_DOWN = 'down';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_HEALTHY;

    #[ORM\Column(nullable: true)]
    private ?int $responseTime = null; // in milliseconds

    #[ORM\Column(nullable: true)]
    private ?float $cpuUsage = null; // percentage (0-100)

    #[ORM\Column(nullable: true)]
    private ?float $memoryUsage = null; // percentage (0-100)

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $memoryUsageBytes = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $memoryLimitBytes = null;

    #[ORM\Column(nullable: true)]
    private ?float $diskUsage = null; // percentage (0-100)

    #[ORM\Column]
    private ?bool $isContainerRunning = false;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $checkedAt = null;

    public function __construct()
    {
        $this->checkedAt = new \DateTimeImmutable();
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getResponseTime(): ?int
    {
        return $this->responseTime;
    }

    public function setResponseTime(?int $responseTime): static
    {
        $this->responseTime = $responseTime;
        return $this;
    }

    public function getCpuUsage(): ?float
    {
        return $this->cpuUsage;
    }

    public function setCpuUsage(?float $cpuUsage): static
    {
        $this->cpuUsage = $cpuUsage;
        return $this;
    }

    public function getMemoryUsage(): ?float
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?float $memoryUsage): static
    {
        $this->memoryUsage = $memoryUsage;
        return $this;
    }

    public function getMemoryUsageBytes(): ?int
    {
        return $this->memoryUsageBytes;
    }

    public function setMemoryUsageBytes(?int $memoryUsageBytes): static
    {
        $this->memoryUsageBytes = $memoryUsageBytes;
        return $this;
    }

    public function getMemoryLimitBytes(): ?int
    {
        return $this->memoryLimitBytes;
    }

    public function setMemoryLimitBytes(?int $memoryLimitBytes): static
    {
        $this->memoryLimitBytes = $memoryLimitBytes;
        return $this;
    }

    public function getDiskUsage(): ?float
    {
        return $this->diskUsage;
    }

    public function setDiskUsage(?float $diskUsage): static
    {
        $this->diskUsage = $diskUsage;
        return $this;
    }

    public function isContainerRunning(): bool
    {
        return $this->isContainerRunning;
    }

    public function setIsContainerRunning(bool $isContainerRunning): static
    {
        $this->isContainerRunning = $isContainerRunning;
        return $this;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): static
    {
        $this->httpStatusCode = $httpStatusCode;
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

    public function getCheckedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): static
    {
        $this->checkedAt = $checkedAt;
        return $this;
    }

    public function isHealthy(): bool
    {
        return $this->status === self::STATUS_HEALTHY;
    }

    public function isDegraded(): bool
    {
        return $this->status === self::STATUS_DEGRADED;
    }

    public function isDown(): bool
    {
        return $this->status === self::STATUS_DOWN;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_HEALTHY => 'bg-green-500/20 text-green-400',
            self::STATUS_DEGRADED => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_DOWN => 'bg-red-500/20 text-red-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getFormattedMemoryUsage(): string
    {
        if ($this->memoryUsageBytes === null) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->memoryUsageBytes;
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
