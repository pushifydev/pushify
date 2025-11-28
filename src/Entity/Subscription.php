<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscription_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_subscription_status', columns: ['status'])]
class Subscription
{
    // Subscription Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUSPENDED = 'suspended';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iyzicoSubscriptionReferenceCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iyzicoCustomerReferenceCode = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 10)]
    private ?string $currency = 'TRY';

    #[ORM\Column(length: 20)]
    private ?string $billingCycle = 'monthly';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodStart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $currentPeriodEnd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cancelledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $trialEndsAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
        $this->currency = 'EUR';
        $this->billingCycle = 'monthly';
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

    public function getIyzicoSubscriptionReferenceCode(): ?string
    {
        return $this->iyzicoSubscriptionReferenceCode;
    }

    public function setIyzicoSubscriptionReferenceCode(?string $iyzicoSubscriptionReferenceCode): static
    {
        $this->iyzicoSubscriptionReferenceCode = $iyzicoSubscriptionReferenceCode;
        return $this;
    }

    public function getIyzicoCustomerReferenceCode(): ?string
    {
        return $this->iyzicoCustomerReferenceCode;
    }

    public function setIyzicoCustomerReferenceCode(?string $iyzicoCustomerReferenceCode): static
    {
        $this->iyzicoCustomerReferenceCode = $iyzicoCustomerReferenceCode;
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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getBillingCycle(): ?string
    {
        return $this->billingCycle;
    }

    public function setBillingCycle(string $billingCycle): static
    {
        $this->billingCycle = $billingCycle;
        return $this;
    }

    public function getCurrentPeriodStart(): ?\DateTimeInterface
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(\DateTimeInterface $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;
        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeInterface
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(\DateTimeInterface $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeInterface $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getTrialEndsAt(): ?\DateTimeInterface
    {
        return $this->trialEndsAt;
    }

    public function setTrialEndsAt(?\DateTimeInterface $trialEndsAt): static
    {
        $this->trialEndsAt = $trialEndsAt;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isOnTrial(): bool
    {
        return $this->trialEndsAt && $this->trialEndsAt > new \DateTime();
    }

    public function getDaysUntilRenewal(): int
    {
        if (!$this->currentPeriodEnd) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->currentPeriodEnd);
        return (int) $diff->days;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-green-500/20 text-green-400',
            self::STATUS_CANCELLED => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_EXPIRED => 'bg-red-500/20 text-red-400',
            self::STATUS_PENDING => 'bg-blue-500/20 text-blue-400',
            self::STATUS_SUSPENDED => 'bg-orange-500/20 text-orange-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }
}
