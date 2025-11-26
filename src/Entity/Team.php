<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(name: 'teams')]
#[ORM\HasLifecycleCallbacks]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\OneToMany(targetEntity: TeamMember::class, mappedBy: 'team', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    #[ORM\OneToMany(targetEntity: TeamInvitation::class, mappedBy: 'team', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invitations;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'team')]
    private Collection $projects;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->projects = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(TeamMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setTeam($this);
        }
        return $this;
    }

    public function removeMember(TeamMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getTeam() === $this) {
                $member->setTeam(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, TeamInvitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if a user is a member of this team
     */
    public function hasMember(User $user): bool
    {
        if ($this->owner === $user) {
            return true;
        }

        foreach ($this->members as $member) {
            if ($member->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a specific member by user
     */
    public function getMember(User $user): ?TeamMember
    {
        foreach ($this->members as $member) {
            if ($member->getUser() === $user) {
                return $member;
            }
        }
        return null;
    }

    /**
     * Check if user has specific role or higher
     */
    public function userHasRole(User $user, string $role): bool
    {
        if ($this->owner === $user) {
            return true; // Owner has all permissions
        }

        $member = $this->getMember($user);
        if (!$member) {
            return false;
        }

        return $member->hasRoleOrHigher($role);
    }

    /**
     * Get member count including owner
     */
    public function getMemberCount(): int
    {
        return $this->members->count() + 1; // +1 for owner
    }

    /**
     * Generate slug from name
     */
    public function generateSlug(): string
    {
        $slug = strtolower(trim($this->name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
