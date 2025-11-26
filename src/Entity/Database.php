<?php

namespace App\Entity;

use App\Repository\DatabaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DatabaseRepository::class)]
#[ORM\Table(name: 'databases')]
#[ORM\Index(name: 'idx_database_project', columns: ['project_id'])]
#[ORM\Index(name: 'idx_database_server', columns: ['server_id'])]
#[ORM\Index(name: 'idx_database_status', columns: ['status'])]
class Database
{
    // Database Types
    public const TYPE_POSTGRESQL = 'postgresql';
    public const TYPE_MYSQL = 'mysql';
    public const TYPE_MARIADB = 'mariadb';
    public const TYPE_MONGODB = 'mongodb';
    public const TYPE_REDIS = 'redis';
    public const TYPE_SQLITE = 'sqlite';

    // Status
    public const STATUS_CREATING = 'creating';
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_ERROR = 'error';
    public const STATUS_DELETING = 'deleting';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $containerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $containerId = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(nullable: true)]
    private ?int $port = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $databaseName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $connectionString = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configuration = null;

    #[ORM\Column(nullable: true)]
    private ?int $memorySizeMb = null;

    #[ORM\Column(nullable: true)]
    private ?float $cpuLimit = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $diskSizeMb = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $stoppedAt = null;

    #[ORM\ManyToOne(inversedBy: 'databases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Server $server = null;

    #[ORM\OneToMany(targetEntity: Backup::class, mappedBy: 'database', orphanRemoval: true)]
    private Collection $backups;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_CREATING;
        $this->backups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getContainerName(): ?string
    {
        return $this->containerName;
    }

    public function setContainerName(?string $containerName): static
    {
        $this->containerName = $containerName;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): static
    {
        $this->port = $port;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(?string $databaseName): static
    {
        $this->databaseName = $databaseName;
        return $this;
    }

    public function getConnectionString(): ?string
    {
        return $this->connectionString;
    }

    public function setConnectionString(?string $connectionString): static
    {
        $this->connectionString = $connectionString;
        return $this;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): static
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getMemorySizeMb(): ?int
    {
        return $this->memorySizeMb;
    }

    public function setMemorySizeMb(?int $memorySizeMb): static
    {
        $this->memorySizeMb = $memorySizeMb;
        return $this;
    }

    public function getCpuLimit(): ?float
    {
        return $this->cpuLimit;
    }

    public function setCpuLimit(?float $cpuLimit): static
    {
        $this->cpuLimit = $cpuLimit;
        return $this;
    }

    public function getDiskSizeMb(): ?string
    {
        return $this->diskSizeMb;
    }

    public function setDiskSizeMb(?string $diskSizeMb): static
    {
        $this->diskSizeMb = $diskSizeMb;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getStoppedAt(): ?\DateTimeInterface
    {
        return $this->stoppedAt;
    }

    public function setStoppedAt(?\DateTimeInterface $stoppedAt): static
    {
        $this->stoppedAt = $stoppedAt;
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

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): static
    {
        $this->server = $server;
        return $this;
    }

    // Helper Methods

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    public function hasError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_RUNNING => 'bg-green-500/20 text-green-400',
            self::STATUS_STOPPED => 'bg-gray-500/20 text-gray-400',
            self::STATUS_CREATING => 'bg-blue-500/20 text-blue-400',
            self::STATUS_DELETING => 'bg-red-500/20 text-red-400',
            self::STATUS_ERROR => 'bg-red-500/20 text-red-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getTypeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_POSTGRESQL => '🐘',
            self::TYPE_MYSQL, self::TYPE_MARIADB => '🐬',
            self::TYPE_MONGODB => '🍃',
            self::TYPE_REDIS => '📦',
            self::TYPE_SQLITE => '💾',
            default => '🗄️',
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_POSTGRESQL => 'PostgreSQL',
            self::TYPE_MYSQL => 'MySQL',
            self::TYPE_MARIADB => 'MariaDB',
            self::TYPE_MONGODB => 'MongoDB',
            self::TYPE_REDIS => 'Redis',
            self::TYPE_SQLITE => 'SQLite',
            default => ucfirst($this->type),
        };
    }

    public function getDefaultPort(): int
    {
        return match ($this->type) {
            self::TYPE_POSTGRESQL => 5432,
            self::TYPE_MYSQL, self::TYPE_MARIADB => 3306,
            self::TYPE_MONGODB => 27017,
            self::TYPE_REDIS => 6379,
            default => 5432,
        };
    }

    public function generateConnectionString(): string
    {
        // Use server IP if server is configured, otherwise localhost
        $host = $this->server ? $this->server->getIpAddress() : 'localhost';
        $port = $this->port ?? $this->getDefaultPort();

        return match ($this->type) {
            self::TYPE_POSTGRESQL => sprintf(
                'postgresql://%s:%s@%s:%d/%s',
                $this->username,
                $this->password,
                $host,
                $port,
                $this->databaseName ?? $this->name
            ),
            self::TYPE_MYSQL, self::TYPE_MARIADB => sprintf(
                'mysql://%s:%s@%s:%d/%s',
                $this->username,
                $this->password,
                $host,
                $port,
                $this->databaseName ?? $this->name
            ),
            self::TYPE_MONGODB => sprintf(
                'mongodb://%s:%s@%s:%d/%s',
                $this->username,
                $this->password,
                $host,
                $port,
                $this->databaseName ?? $this->name
            ),
            self::TYPE_REDIS => sprintf(
                'redis://:%s@%s:%d',
                $this->password,
                $host,
                $port
            ),
            default => '',
        };
    }

    public function getUptime(): ?string
    {
        if (!$this->startedAt || !$this->isRunning()) {
            return null;
        }

        $interval = $this->startedAt->diff(new \DateTime());

        if ($interval->days > 0) {
            return $interval->days . ' days';
        } elseif ($interval->h > 0) {
            return $interval->h . ' hours';
        } elseif ($interval->i > 0) {
            return $interval->i . ' minutes';
        }

        return 'Just now';
    }

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_POSTGRESQL => 'PostgreSQL',
            self::TYPE_MYSQL => 'MySQL',
            self::TYPE_MARIADB => 'MariaDB',
            self::TYPE_MONGODB => 'MongoDB',
            self::TYPE_REDIS => 'Redis',
        ];
    }

    public static function getAvailableVersions(string $type): array
    {
        return match ($type) {
            self::TYPE_POSTGRESQL => ['16', '15', '14', '13', '12'],
            self::TYPE_MYSQL => ['8.0', '5.7'],
            self::TYPE_MARIADB => ['11', '10.11', '10.6'],
            self::TYPE_MONGODB => ['7.0', '6.0', '5.0'],
            self::TYPE_REDIS => ['7.2', '7.0', '6.2'],
            default => ['latest'],
        };
    }

    /**
     * @return Collection<int, Backup>
     */
    public function getBackups(): Collection
    {
        return $this->backups;
    }

    public function addBackup(Backup $backup): static
    {
        if (!$this->backups->contains($backup)) {
            $this->backups->add($backup);
            $backup->setDatabase($this);
        }

        return $this;
    }

    public function removeBackup(Backup $backup): static
    {
        if ($this->backups->removeElement($backup)) {
            // set the owning side to null (unless already changed)
            if ($backup->getDatabase() === $this) {
                $backup->setDatabase(null);
            }
        }

        return $this;
    }
}
