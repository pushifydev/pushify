<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'servers')]
class Server
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTING = 'connecting';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';

    public const PROVIDER_CUSTOM = 'custom';
    public const PROVIDER_DIGITALOCEAN = 'digitalocean';
    public const PROVIDER_HETZNER = 'hetzner';
    public const PROVIDER_AWS = 'aws';
    public const PROVIDER_LINODE = 'linode';
    public const PROVIDER_VULTR = 'vultr';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private int $sshPort = 22;

    #[ORM\Column(length: 50)]
    private string $sshUser = 'root';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sshPrivateKey = null;

    #[ORM\Column(length: 20)]
    private ?string $provider = self::PROVIDER_CUSTOM;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerId = null; // External provider server ID

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?bool $dockerInstalled = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $dockerVersion = null;

    #[ORM\Column(nullable: true)]
    private ?int $cpuCores = null;

    #[ORM\Column(nullable: true)]
    private ?int $memoryMb = null;

    #[ORM\Column(nullable: true)]
    private ?int $diskGb = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $os = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastConnectedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getSshPort(): int
    {
        return $this->sshPort;
    }

    public function setSshPort(int $sshPort): static
    {
        $this->sshPort = $sshPort;
        return $this;
    }

    public function getSshUser(): string
    {
        return $this->sshUser;
    }

    public function setSshUser(string $sshUser): static
    {
        $this->sshUser = $sshUser;
        return $this;
    }

    public function getSshPrivateKey(): ?string
    {
        return $this->sshPrivateKey;
    }

    public function setSshPrivateKey(?string $sshPrivateKey): static
    {
        $this->sshPrivateKey = $sshPrivateKey;
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

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): static
    {
        $this->providerId = $providerId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isDockerInstalled(): ?bool
    {
        return $this->dockerInstalled;
    }

    public function setDockerInstalled(?bool $dockerInstalled): static
    {
        $this->dockerInstalled = $dockerInstalled;
        return $this;
    }

    public function getDockerVersion(): ?string
    {
        return $this->dockerVersion;
    }

    public function setDockerVersion(?string $dockerVersion): static
    {
        $this->dockerVersion = $dockerVersion;
        return $this;
    }

    public function getCpuCores(): ?int
    {
        return $this->cpuCores;
    }

    public function setCpuCores(?int $cpuCores): static
    {
        $this->cpuCores = $cpuCores;
        return $this;
    }

    public function getMemoryMb(): ?int
    {
        return $this->memoryMb;
    }

    public function setMemoryMb(?int $memoryMb): static
    {
        $this->memoryMb = $memoryMb;
        return $this;
    }

    public function getMemoryGb(): ?float
    {
        return $this->memoryMb ? round($this->memoryMb / 1024, 1) : null;
    }

    public function getDiskGb(): ?int
    {
        return $this->diskGb;
    }

    public function setDiskGb(?int $diskGb): static
    {
        $this->diskGb = $diskGb;
        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): static
    {
        $this->os = $os;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
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

    public function getLastConnectedAt(): ?\DateTimeImmutable
    {
        return $this->lastConnectedAt;
    }

    public function setLastConnectedAt(?\DateTimeImmutable $lastConnectedAt): static
    {
        $this->lastConnectedAt = $lastConnectedAt;
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'bg-green-500/20 text-green-400',
            self::STATUS_CONNECTING => 'bg-yellow-500/20 text-yellow-400',
            self::STATUS_PENDING => 'bg-blue-500/20 text-blue-400',
            self::STATUS_ERROR => 'bg-red-500/20 text-red-400',
            default => 'bg-gray-500/20 text-gray-400',
        };
    }

    public function getProviderLabel(): string
    {
        return match ($this->provider) {
            self::PROVIDER_DIGITALOCEAN => 'DigitalOcean',
            self::PROVIDER_HETZNER => 'Hetzner',
            self::PROVIDER_AWS => 'AWS',
            self::PROVIDER_LINODE => 'Linode',
            self::PROVIDER_VULTR => 'Vultr',
            default => 'Custom Server',
        };
    }

    public static function getProviderChoices(): array
    {
        return [
            'Custom Server' => self::PROVIDER_CUSTOM,
            'DigitalOcean' => self::PROVIDER_DIGITALOCEAN,
            'Hetzner' => self::PROVIDER_HETZNER,
            'AWS' => self::PROVIDER_AWS,
            'Linode' => self::PROVIDER_LINODE,
            'Vultr' => self::PROVIDER_VULTR,
        ];
    }

    public function getHost(): string
    {
        return $this->ipAddress ?? 'localhost';
    }
}
