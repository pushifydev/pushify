<?php

namespace App\Repository;

use App\Entity\HealthCheck;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthCheck>
 */
class HealthCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthCheck::class);
    }

    /**
     * Get latest health check for a project
     */
    public function findLatestForProject(Project $project): ?HealthCheck
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->setParameter('project', $project)
            ->orderBy('h.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get health checks for a project within a time range
     *
     * @return HealthCheck[]
     */
    public function findByProjectAndTimeRange(
        Project $project,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->andWhere('h.checkedAt >= :from')
            ->andWhere('h.checkedAt <= :to')
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('h.checkedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent health checks for a project (last N checks)
     *
     * @return HealthCheck[]
     */
    public function findRecentForProject(Project $project, int $limit = 100): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.project = :project')
            ->setParameter('project', $project)
            ->orderBy('h.checkedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate uptime percentage for a project
     */
    public function calculateUptime(Project $project, \DateTimeImmutable $since): float
    {
        $total = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.project = :project')
            ->andWhere('h.checkedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total == 0) {
            return 100.0;
        }

        $healthy = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.project = :project')
            ->andWhere('h.checkedAt >= :since')
            ->andWhere('h.status = :status')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->setParameter('status', HealthCheck::STATUS_HEALTHY)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($healthy / $total) * 100, 2);
    }

    /**
     * Get average response time for a project
     */
    public function getAverageResponseTime(Project $project, \DateTimeImmutable $since): ?float
    {
        $result = $this->createQueryBuilder('h')
            ->select('AVG(h.responseTime)')
            ->andWhere('h.project = :project')
            ->andWhere('h.checkedAt >= :since')
            ->andWhere('h.responseTime IS NOT NULL')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round($result, 2) : null;
    }

    /**
     * Delete old health checks (cleanup)
     */
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->andWhere('h.checkedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
