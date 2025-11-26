<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'projects')]
#[ORM\HasLifecycleCallbacks]
class Project
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_BUILDING = 'building';
    public const STATUS_DEPLOYED = 'deployed';
    public const STATUS_FAILED = 'failed';

    public const FRAMEWORK_NEXTJS = 'nextjs';
    public const FRAMEWORK_REACT = 'react';
    public const FRAMEWORK_VUE = 'vue';
    public const FRAMEWORK_NUXT = 'nuxt';
    public const FRAMEWORK_SVELTE = 'svelte';
    public const FRAMEWORK_LARAVEL = 'laravel';
    public const FRAMEWORK_SYMFONY = 'symfony';
    public const FRAMEWORK_NODEJS = 'nodejs';
    public const FRAMEWORK_STATIC = 'static';
    public const FRAMEWORK_OTHER = 'other';

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

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Team $team = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repositoryUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repositoryName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $branch = 'main';

    #[ORM\Column(length: 50)]
    private ?string $framework = self::FRAMEWORK_OTHER;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buildCommand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $installCommand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $startCommand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $outputDirectory = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rootDirectory = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productionUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customDomain = null;

    #[ORM\Column]
    private ?bool $autoDeployEnabled = true;

    #[ORM\Column]
    private ?bool $previewDeploymentsEnabled = false;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $webhookSecret = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $githubWebhookSecret = null;

    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Server $server = null;

    #[ORM\Column(nullable: true)]
    private ?int $containerPort = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $containerId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastDeployedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: Database::class, mappedBy: 'project', cascade: ['persist', 'remove'])]
    private Collection $databases;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->webhookSecret = bin2hex(random_bytes(32));
        $this->databases = new ArrayCollection();
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function setRepositoryUrl(?string $repositoryUrl): static
    {
        $this->repositoryUrl = $repositoryUrl;
        return $this;
    }

    public function getRepositoryName(): ?string
    {
        return $this->repositoryName;
    }

    public function setRepositoryName(?string $repositoryName): static
    {
        $this->repositoryName = $repositoryName;
        return $this;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = $branch;
        return $this;
    }

    public function getFramework(): ?string
    {
        return $this->framework;
    }

    public function setFramework(string $framework): static
    {
        $this->framework = $framework;
        return $this;
    }

    public function getBuildCommand(): ?string
    {
        return $this->buildCommand;
    }

    public function setBuildCommand(?string $buildCommand): static
    {
        $this->buildCommand = $buildCommand;
        return $this;
    }

    public function getInstallCommand(): ?string
    {
        return $this->installCommand;
    }

    public function setInstallCommand(?string $installCommand): static
    {
        $this->installCommand = $installCommand;
        return $this;
    }

    public function getStartCommand(): ?string
    {
        return $this->startCommand;
    }

    public function setStartCommand(?string $startCommand): static
    {
        $this->startCommand = $startCommand;
        return $this;
    }

    public function getOutputDirectory(): ?string
    {
        return $this->outputDirectory;
    }

    public function setOutputDirectory(?string $outputDirectory): static
    {
        $this->outputDirectory = $outputDirectory;
        return $this;
    }

    public function getRootDirectory(): ?string
    {
        return $this->rootDirectory;
    }

    public function setRootDirectory(?string $rootDirectory): static
    {
        $this->rootDirectory = $rootDirectory;
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

    public function getProductionUrl(): ?string
    {
        return $this->productionUrl;
    }

    public function setProductionUrl(?string $productionUrl): static
    {
        $this->productionUrl = $productionUrl;
        return $this;
    }

    public function getCustomDomain(): ?string
    {
        return $this->customDomain;
    }

    public function setCustomDomain(?string $customDomain): static
    {
        $this->customDomain = $customDomain;
        return $this;
    }

    public function isAutoDeployEnabled(): ?bool
    {
        return $this->autoDeployEnabled;
    }

    public function setAutoDeployEnabled(bool $autoDeployEnabled): static
    {
        $this->autoDeployEnabled = $autoDeployEnabled;
        return $this;
    }

    public function isPreviewDeploymentsEnabled(): ?bool
    {
        return $this->previewDeploymentsEnabled;
    }

    public function setPreviewDeploymentsEnabled(bool $previewDeploymentsEnabled): static
    {
        $this->previewDeploymentsEnabled = $previewDeploymentsEnabled;
        return $this;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(string $webhookSecret): static
    {
        $this->webhookSecret = $webhookSecret;
        return $this;
    }

    public function regenerateWebhookSecret(): static
    {
        $this->webhookSecret = bin2hex(random_bytes(32));
        return $this;
    }

    public function getGithubWebhookSecret(): ?string
    {
        return $this->githubWebhookSecret;
    }

    public function setGithubWebhookSecret(?string $githubWebhookSecret): static
    {
        $this->githubWebhookSecret = $githubWebhookSecret;
        return $this;
    }

    public function getWebhookUrl(): string
    {
        return '/webhooks/github/' . $this->webhookSecret;
    }

    public function getLastDeployedAt(): ?\DateTimeImmutable
    {
        return $this->lastDeployedAt;
    }

    public function setLastDeployedAt(?\DateTimeImmutable $lastDeployedAt): static
    {
        $this->lastDeployedAt = $lastDeployedAt;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public static function getFrameworkChoices(): array
    {
        return [
            'Next.js' => self::FRAMEWORK_NEXTJS,
            'React' => self::FRAMEWORK_REACT,
            'Vue.js' => self::FRAMEWORK_VUE,
            'Nuxt' => self::FRAMEWORK_NUXT,
            'Svelte' => self::FRAMEWORK_SVELTE,
            'Laravel' => self::FRAMEWORK_LARAVEL,
            'Symfony' => self::FRAMEWORK_SYMFONY,
            'Node.js' => self::FRAMEWORK_NODEJS,
            'Static HTML' => self::FRAMEWORK_STATIC,
            'Other' => self::FRAMEWORK_OTHER,
        ];
    }

    public function getFrameworkLabel(): string
    {
        return array_search($this->framework, self::getFrameworkChoices()) ?: 'Other';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DEPLOYED => 'bg-green-500/20 text-green-400',
            self::STATUS_BUILDING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): static
    {
        $this->server = $server;
        return $this;
    }

    public function getContainerPort(): ?int
    {
        return $this->containerPort;
    }

    public function setContainerPort(?int $containerPort): static
    {
        $this->containerPort = $containerPort;
        return $this;
    }

    public function getContainerId(): ?string
    {
        return $this->containerId;
    }

    public function setContainerId(?string $containerId): static
    {
        $this->containerId = $containerId;
        return $this;
    }

    public function getDeploymentUrl(): ?string
    {
        if (!$this->server || !$this->containerPort) {
            return null;
        }
        return 'http://' . $this->server->getIpAddress() . ':' . $this->containerPort;
    }

    /**
     * Get GitHub repository full name (owner/repo) from repository URL
     */
    public function getGithubRepo(): ?string
    {
        if (!$this->repositoryUrl) {
            return null;
        }

        // Handle both HTTPS and SSH URLs
        // https://github.com/owner/repo.git or git@github.com:owner/repo.git
        if (preg_match('#github\.com[:/]([^/]+/[^/\.]+)(?:\.git)?$#', $this->repositoryUrl, $matches)) {
            return $matches[1];
        }

        return $this->repositoryName;
    }

    public function getUser(): ?User
    {
        return $this->owner;
    }

    /**
     * @return Collection<int, Database>
     */
    public function getDatabases(): Collection
    {
        return $this->databases;
    }

    public function addDatabase(Database $database): static
    {
        if (!$this->databases->contains($database)) {
            $this->databases->add($database);
            $database->setProject($this);
        }

        return $this;
    }

    public function removeDatabase(Database $database): static
    {
        if ($this->databases->removeElement($database)) {
            if ($database->getProject() === $this) {
                $database->setProject(null);
            }
        }

        return $this;
    }
}
