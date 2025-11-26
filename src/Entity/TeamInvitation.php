<?php

namespace App\Entity;

use App\Repository\TeamInvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamInvitationRepository::class)]
#[ORM\Table(name: 'team_invitations')]
#[ORM\HasLifecycleCallbacks]
class TeamInvitation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 50)]
    private ?string $role = TeamMember::ROLE_DEVELOPER;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $invitedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $invitedUser = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+7 days');
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
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

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;
        return $this;
    }

    public function getInvitedUser(): ?User
    {
        return $this->invitedUser;
    }

    public function setInvitedUser(?User $invitedUser): static
    {
        $this->invitedUser = $invitedUser;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): static
    {
        $this->respondedAt = $respondedAt;
        return $this;
    }

    /**
     * Check if invitation is still pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if invitation has expired
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Check if invitation can be accepted
     */
    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    /**
     * Accept the invitation
     */
    public function accept(): static
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Decline the invitation
     */
    public function decline(): static
    {
        $this->status = self::STATUS_DECLINED;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Cancel the invitation
     */
    public function cancel(): static
    {
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    /**
     * Mark as expired
     */
    public function markExpired(): static
    {
        $this->status = self::STATUS_EXPIRED;
        return $this;
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_DECLINED => 'Declined',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_ACCEPTED => 'bg-green-500/20 text-green-400',
            self::STATUS_DECLINED => 'bg-red-500/20 text-red-400',
            self::STATUS_EXPIRED => 'bg-gray-500/20 text-gray-400',
            self::STATUS_CANCELLED => 'bg-gray-500/20 text-gray-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    /**
     * Get role label
     */
    public function getRoleLabel(): string
    {
        return match ($this->role) {
            TeamMember::ROLE_VIEWER => 'Viewer',
            TeamMember::ROLE_DEVELOPER => 'Developer',
            TeamMember::ROLE_ADMIN => 'Admin',
            default => 'Unknown',
        };
    }

    /**
     * Get invitation URL
     */
    public function getInvitationUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/teams/invite/' . $this->token;
    }
}
