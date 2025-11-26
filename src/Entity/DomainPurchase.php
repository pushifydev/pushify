<?php

namespace App\Entity;

use App\Repository\DomainPurchaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainPurchaseRepository::class)]
#[ORM\Table(name: 'domain_purchases')]
#[ORM\HasLifecycleCallbacks]
class DomainPurchase
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PROVIDER_NAMECHEAP = 'namecheap';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $domain = null;

    #[ORM\Column(length: 20)]
    private ?string $tld = null;

    #[ORM\Column(length: 50)]
    private ?string $provider = self::PROVIDER_NAMECHEAP;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private int $years = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerDomainId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerOrderId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerTransactionId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $registrantInfo = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $nameservers = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

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

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = strtolower($domain);
        // Extract TLD
        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            $this->tld = array_pop($parts);
        }
        return $this;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(string $tld): static
    {
        $this->tld = $tld;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
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

    public function getYears(): int
    {
        return $this->years;
    }

    public function setYears(int $years): static
    {
        $this->years = $years;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getProviderDomainId(): ?string
    {
        return $this->providerDomainId;
    }

    public function setProviderDomainId(?string $providerDomainId): static
    {
        $this->providerDomainId = $providerDomainId;
        return $this;
    }

    public function getProviderOrderId(): ?string
    {
        return $this->providerOrderId;
    }

    public function setProviderOrderId(?string $providerOrderId): static
    {
        $this->providerOrderId = $providerOrderId;
        return $this;
    }

    public function getProviderTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function setProviderTransactionId(?string $providerTransactionId): static
    {
        $this->providerTransactionId = $providerTransactionId;
        return $this;
    }

    public function getRegistrantInfo(): ?array
    {
        return $this->registrantInfo;
    }

    public function setRegistrantInfo(?array $registrantInfo): static
    {
        $this->registrantInfo = $registrantInfo;
        return $this;
    }

    public function getNameservers(): ?array
    {
        return $this->nameservers;
    }

    public function setNameservers(?array $nameservers): static
    {
        $this->nameservers = $nameservers;
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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
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

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->expiresAt);
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'bg-green-500/20 text-green-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            self::STATUS_PROCESSING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_CANCELLED => 'bg-gray-500/20 text-gray-400',
            default => 'bg-blue-500/20 text-blue-400',
        };
    }
}
