<?php

namespace App\Repository;

use App\Entity\Database;
use App\Entity\Project;
use App\Entity\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Database>
 */
class DatabaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Database::class);
    }

    /**
     * Find all databases for a project
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all running databases for a project
     */
    public function findRunningByProject(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', Database::STATUS_RUNNING)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all databases on a server
     */
    public function findByServer(Server $server): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.server = :server')
            ->setParameter('server', $server)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find database by container name
     */
    public function findByContainerName(string $containerName): ?Database
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.containerName = :containerName')
            ->setParameter('containerName', $containerName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find database by container ID
     */
    public function findByContainerId(string $containerId): ?Database
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.containerId = :containerId')
            ->setParameter('containerId', $containerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get database statistics for a project
     */
    public function getProjectStats(Project $project): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.status, d.type, COUNT(d.id) as count')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->groupBy('d.status, d.type');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'running' => 0,
            'stopped' => 0,
            'error' => 0,
            'by_type' => [],
        ];

        foreach ($results as $result) {
            $count = (int) $result['count'];
            $stats['total'] += $count;

            if ($result['status'] === Database::STATUS_RUNNING) {
                $stats['running'] += $count;
            } elseif ($result['status'] === Database::STATUS_STOPPED) {
                $stats['stopped'] += $count;
            } elseif ($result['status'] === Database::STATUS_ERROR) {
                $stats['error'] += $count;
            }

            $type = $result['type'];
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type] += $count;
        }

        return $stats;
    }

    /**
     * Get total resource usage for a project
     */
    public function getProjectResourceUsage(Project $project): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('
                COALESCE(SUM(d.memorySizeMb), 0) as totalMemory,
                COALESCE(SUM(d.cpuLimit), 0) as totalCpu,
                COALESCE(SUM(d.diskSizeMb), 0) as totalDisk
            ')
            ->andWhere('d.project = :project')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('project', $project)
            ->setParameter('statuses', [Database::STATUS_RUNNING, Database::STATUS_CREATING]);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'memory_mb' => (int) $result['totalMemory'],
            'cpu' => (float) $result['totalCpu'],
            'disk_mb' => (int) $result['totalDisk'],
        ];
    }

    /**
     * Check if a database name exists for a project
     */
    public function existsByNameAndProject(string $name, Project $project, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.name = :name')
            ->andWhere('d.project = :project')
            ->setParameter('name', $name)
            ->setParameter('project', $project);

        if ($excludeId !== null) {
            $qb->andWhere('d.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Find databases that need cleanup (old deleted databases)
     */
    public function findDatabasesForCleanup(\DateTimeInterface $olderThan): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->andWhere('d.createdAt < :olderThan')
            ->setParameter('status', Database::STATUS_DELETING)
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all databases grouped by server
     */
    public function findAllGroupedByServer(): array
    {
        $databases = $this->createQueryBuilder('d')
            ->leftJoin('d.server', 's')
            ->addSelect('s')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($databases as $database) {
            $serverId = $database->getServer() ? $database->getServer()->getId() : 0;
            if (!isset($grouped[$serverId])) {
                $grouped[$serverId] = [
                    'server' => $database->getServer(),
                    'databases' => [],
                ];
            }
            $grouped[$serverId]['databases'][] = $database;
        }

        return $grouped;
    }
}
