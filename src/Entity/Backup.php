<?php

namespace App\Entity;

use App\Repository\BackupRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BackupRepository::class)]
#[ORM\Table(name: 'backups')]
#[ORM\Index(name: 'idx_backup_database', columns: ['database_id'])]
#[ORM\Index(name: 'idx_backup_status', columns: ['status'])]
#[ORM\Index(name: 'idx_backup_created', columns: ['created_at'])]
class Backup
{
    // Backup Types
    public const TYPE_MANUAL = 'manual';
    public const TYPE_SCHEDULED = 'scheduled';
    public const TYPE_PRE_DEPLOYMENT = 'pre_deployment';
    public const TYPE_PRE_UPDATE = 'pre_update';

    // Status
    public const STATUS_CREATING = 'creating';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RESTORING = 'restoring';
    public const STATUS_RESTORED = 'restored';
    public const STATUS_DELETED = 'deleted';

    // Backup Methods
    public const METHOD_DUMP = 'dump';
    public const METHOD_SNAPSHOT = 'snapshot';

    // Compression Types
    public const COMPRESSION_NONE = 'none';
    public const COMPRESSION_GZIP = 'gzip';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(length: 50)]
    private ?string $method = null;

    #[ORM\Column(length: 50)]
    private ?string $compression = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $fileSizeBytes = null;

    #[ORM\Column(nullable: true)]
    private ?int $retentionDays = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $restoredAt = null;

    #[ORM\ManyToOne(inversedBy: 'backups')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Database $database = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_CREATING;
        $this->method = self::METHOD_DUMP;
        $this->compression = self::COMPRESSION_GZIP;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getCompression(): ?string
    {
        return $this->compression;
    }

    public function setCompression(string $compression): static
    {
        $this->compression = $compression;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getFileSizeBytes(): ?string
    {
        return $this->fileSizeBytes;
    }

    public function setFileSizeBytes(?string $fileSizeBytes): static
    {
        $this->fileSizeBytes = $fileSizeBytes;
        return $this;
    }

    public function getRetentionDays(): ?int
    {
        return $this->retentionDays;
    }

    public function setRetentionDays(?int $retentionDays): static
    {
        $this->retentionDays = $retentionDays;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getRestoredAt(): ?\DateTimeInterface
    {
        return $this->restoredAt;
    }

    public function setRestoredAt(?\DateTimeInterface $restoredAt): static
    {
        $this->restoredAt = $restoredAt;
        return $this;
    }

    public function getDatabase(): ?Database
    {
        return $this->database;
    }

    public function setDatabase(?Database $database): static
    {
        $this->database = $database;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    // Helper Methods

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
        if (!$this->expiresAt) {
            return false;
        }

        return $this->expiresAt < new \DateTime();
    }

    public function getFileSizeMb(): ?float
    {
        if (!$this->fileSizeBytes) {
            return null;
        }

        return round((int)$this->fileSizeBytes / 1024 / 1024, 2);
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->fileSizeBytes) {
            return 'N/A';
        }

        $bytes = (int)$this->fileSizeBytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } elseif ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'bg-green-500/20 text-green-400',
            self::STATUS_CREATING => 'bg-blue-500/20 text-blue-400',
            self::STATUS_RESTORING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_RESTORED => 'bg-green-500/20 text-green-400',
            self::STATUS_FAILED => 'bg-red-500/20 text-red-400',
            self::STATUS_DELETED => 'bg-gray-500/20 text-gray-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_MANUAL => 'bg-blue-500/20 text-blue-400',
            self::TYPE_SCHEDULED => 'bg-purple-500/20 text-purple-400',
            self::TYPE_PRE_DEPLOYMENT => 'bg-orange-500/20 text-orange-400',
            self::TYPE_PRE_UPDATE => 'bg-yellow-500/20 text-yellow-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_SCHEDULED => 'Scheduled',
            self::TYPE_PRE_DEPLOYMENT => 'Pre-Deployment',
            self::TYPE_PRE_UPDATE => 'Pre-Update',
            default => ucfirst($this->type),
        };
    }

    public function generateBackupFilename(): string
    {
        $dbName = $this->database->getName();
        $timestamp = $this->createdAt->format('Y-m-d_H-i-s');
        $extension = $this->compression === self::COMPRESSION_GZIP ? '.sql.gz' : '.sql';

        return sprintf('%s_%s%s', $dbName, $timestamp, $extension);
    }

    public function calculateExpiresAt(): void
    {
        if ($this->retentionDays && $this->createdAt) {
            $expiryDate = clone $this->createdAt;
            $expiryDate->modify("+{$this->retentionDays} days");
            $this->expiresAt = $expiryDate;
        }
    }
}
