<?php

namespace App\Entity;

use App\Repository\AlertRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRuleRepository::class)]
#[ORM\Table(name: 'alert_rules')]
#[ORM\Index(columns: ['project_id', 'enabled'], name: 'idx_alert_project_enabled')]
class AlertRule
{
    public const METRIC_CPU = 'cpu';
    public const METRIC_MEMORY = 'memory';
    public const METRIC_DISK = 'disk';
    public const METRIC_RESPONSE_TIME = 'response_time';
    public const METRIC_UPTIME = 'uptime';

    public const OPERATOR_GREATER_THAN = '>';
    public const OPERATOR_LESS_THAN = '<';
    public const OPERATOR_EQUALS = '=';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WEBHOOK = 'webhook';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $metric = null; // cpu, memory, disk, response_time, uptime

    #[ORM\Column(length: 10)]
    private ?string $operator = '>'; // >, <, =

    #[ORM\Column]
    private ?float $threshold = null;

    #[ORM\Column]
    private ?int $duration = 1; // minutes - how long condition must persist

    #[ORM\Column(length: 50)]
    private ?string $channel = self::CHANNEL_EMAIL; // email, webhook

    #[ORM\Column]
    private ?bool $enabled = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTriggeredAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getMetric(): ?string
    {
        return $this->metric;
    }

    public function setMetric(string $metric): static
    {
        $this->metric = $metric;
        return $this;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): static
    {
        $this->operator = $operator;
        return $this;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    public function setThreshold(float $threshold): static
    {
        $this->threshold = $threshold;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
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

    public function getLastTriggeredAt(): ?\DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function setLastTriggeredAt(?\DateTimeImmutable $lastTriggeredAt): static
    {
        $this->lastTriggeredAt = $lastTriggeredAt;
        return $this;
    }

    public function getConditionText(): string
    {
        $metricLabel = match ($this->metric) {
            self::METRIC_CPU => 'CPU Usage',
            self::METRIC_MEMORY => 'Memory Usage',
            self::METRIC_DISK => 'Disk Usage',
            self::METRIC_RESPONSE_TIME => 'Response Time',
            self::METRIC_UPTIME => 'Uptime',
            default => $this->metric,
        };

        $unit = match ($this->metric) {
            self::METRIC_RESPONSE_TIME => 'ms',
            self::METRIC_UPTIME => '%',
            default => '%',
        };

        return "{$metricLabel} {$this->operator} {$this->threshold}{$unit}";
    }
}
