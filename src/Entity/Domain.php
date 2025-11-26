<?php

namespace App\Entity;

use App\Repository\DomainRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'domains')]
#[ORM\HasLifecycleCallbacks]
class Domain
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_SSL_PENDING = 'ssl_pending';
    public const STATUS_SSL_ACTIVE = 'ssl_active';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    private ?string $domain = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private bool $isPrimary = false;

    #[ORM\Column(nullable: true)]
    private ?bool $dnsVerified = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dnsVerifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $sslEnabled = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sslIssuedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sslExpiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        // Normalize domain (lowercase, no trailing dots)
        $this->domain = strtolower(rtrim($domain, '.'));
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

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function isDnsVerified(): ?bool
    {
        return $this->dnsVerified;
    }

    public function setDnsVerified(?bool $dnsVerified): static
    {
        $this->dnsVerified = $dnsVerified;
        return $this;
    }

    public function getDnsVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->dnsVerifiedAt;
    }

    public function setDnsVerifiedAt(?\DateTimeImmutable $dnsVerifiedAt): static
    {
        $this->dnsVerifiedAt = $dnsVerifiedAt;
        return $this;
    }

    public function isSslEnabled(): ?bool
    {
        return $this->sslEnabled;
    }

    public function setSslEnabled(?bool $sslEnabled): static
    {
        $this->sslEnabled = $sslEnabled;
        return $this;
    }

    public function getSslIssuedAt(): ?\DateTimeImmutable
    {
        return $this->sslIssuedAt;
    }

    public function setSslIssuedAt(?\DateTimeImmutable $sslIssuedAt): static
    {
        $this->sslIssuedAt = $sslIssuedAt;
        return $this;
    }

    public function getSslExpiresAt(): ?\DateTimeImmutable
    {
        return $this->sslExpiresAt;
    }

    public function setSslExpiresAt(?\DateTimeImmutable $sslExpiresAt): static
    {
        $this->sslExpiresAt = $sslExpiresAt;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SSL_ACTIVE => 'bg-green-500/20 text-green-400',
            self::STATUS_VERIFIED, self::STATUS_SSL_PENDING => 'bg-blue-500/20 text-blue-400',
            self::STATUS_VERIFYING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_VERIFYING => 'Verifying DNS',
            self::STATUS_VERIFIED => 'DNS Verified',
            self::STATUS_SSL_PENDING => 'SSL Pending',
            self::STATUS_SSL_ACTIVE => 'SSL Active',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    public function getFullUrl(): string
    {
        $protocol = $this->sslEnabled ? 'https' : 'http';
        return $protocol . '://' . $this->domain;
    }

    public function isSslExpiringSoon(): bool
    {
        if (!$this->sslExpiresAt) {
            return false;
        }
        $daysUntilExpiry = $this->sslExpiresAt->diff(new \DateTimeImmutable())->days;
        return $daysUntilExpiry <= 30;
    }
}
