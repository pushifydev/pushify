<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /**
     * Get recent alerts for a project
     *
     * @return Alert[]
     */
    public function findRecentForProject(Project $project, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.project = :project')
            ->setParameter('project', $project)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unresolved alerts for a project
     *
     * @return Alert[]
     */
    public function findUnresolvedForProject(Project $project): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.project = :project')
            ->andWhere('a.resolved = false')
            ->setParameter('project', $project)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unresolved alerts for a project
     */
    public function countUnresolvedForProject(Project $project): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.project = :project')
            ->andWhere('a.resolved = false')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get alerts that haven't been notified yet
     *
     * @return Alert[]
     */
    public function findPendingNotifications(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.notificationSent = false')
            ->andWhere('a.resolved = false')
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Auto-resolve old alerts
     */
    public function autoResolveOldAlerts(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.resolved', true)
            ->set('a.resolvedAt', ':now')
            ->andWhere('a.resolved = false')
            ->andWhere('a.createdAt < :before')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
