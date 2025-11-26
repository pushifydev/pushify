<?php

namespace App\Entity;

use App\Repository\TeamMemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\Table(name: 'team_members')]
#[ORM\UniqueConstraint(name: 'unique_team_user', columns: ['team_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class TeamMember
{
    // Role constants - ordered by permission level
    public const ROLE_VIEWER = 'viewer';      // Can view projects and deployments
    public const ROLE_DEVELOPER = 'developer'; // Can deploy and manage env vars
    public const ROLE_ADMIN = 'admin';         // Can manage team members, projects

    public const ROLES = [
        self::ROLE_VIEWER,
        self::ROLE_DEVELOPER,
        self::ROLE_ADMIN,
    ];

    public const ROLE_HIERARCHY = [
        self::ROLE_VIEWER => 1,
        self::ROLE_DEVELOPER => 2,
        self::ROLE_ADMIN => 3,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $role = self::ROLE_DEVELOPER;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, self::ROLES)) {
            throw new \InvalidArgumentException('Invalid role: ' . $role);
        }
        $this->role = $role;
        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if member has the given role or higher
     */
    public function hasRoleOrHigher(string $role): bool
    {
        $memberLevel = self::ROLE_HIERARCHY[$this->role] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$role] ?? 0;

        return $memberLevel >= $requiredLevel;
    }

    /**
     * Check if member is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if member can deploy
     */
    public function canDeploy(): bool
    {
        return $this->hasRoleOrHigher(self::ROLE_DEVELOPER);
    }

    /**
     * Check if member can manage team
     */
    public function canManageTeam(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Get role label for display
     */
    public function getRoleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_VIEWER => 'Viewer',
            self::ROLE_DEVELOPER => 'Developer',
            self::ROLE_ADMIN => 'Admin',
            default => 'Unknown',
        };
    }

    /**
     * Get role badge class
     */
    public function getRoleBadgeClass(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'bg-purple-500/20 text-purple-400',
            self::ROLE_DEVELOPER => 'bg-blue-500/20 text-blue-400',
            self::ROLE_VIEWER => 'bg-gray-500/20 text-gray-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    /**
     * Get available roles for selection
     */
    public static function getRoleChoices(): array
    {
        return [
            'Viewer' => self::ROLE_VIEWER,
            'Developer' => self::ROLE_DEVELOPER,
            'Admin' => self::ROLE_ADMIN,
        ];
    }
}
