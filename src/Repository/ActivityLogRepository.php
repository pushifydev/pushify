<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find recent activity for a user (across all their projects/teams)
     * @return ActivityLog[]
     */
    public function findRecentByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activity for a specific project
     * @return ActivityLog[]
     */
    public function findByProject(Project $project, int $limit = 50): array
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
     * Find activity for a team
     * @return ActivityLog[]
     */
    public function findByTeam(Team $team, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.team = :team')
            ->setParameter('team', $team)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activity visible to a user (their own + their projects + their teams)
     * @return ActivityLog[]
     */
    public function findVisibleToUser(User $user, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.project', 'p')
            ->leftJoin('a.team', 't')
            ->leftJoin('t.members', 'tm')
            ->where('a.user = :user')
            ->orWhere('p.owner = :user')
            ->orWhere('tm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find activity by action type
     * @return ActivityLog[]
     */
    public function findByAction(string $action, ?User $user = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.action = :action')
            ->setParameter('action', $action)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('a.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find activity for a specific entity
     * @return ActivityLog[]
     */
    public function findByEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activity statistics for a user
     */
    public function getStatsForUser(User $user, \DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.action, COUNT(a.id) as count')
            ->andWhere('a.user = :user')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('a.action')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($result as $row) {
            $stats[$row['action']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get activity timeline grouped by date
     * @return array<string, ActivityLog[]>
     */
    public function getTimelineForUser(User $user, int $days = 7): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $activities = $this->createQueryBuilder('a')
            ->leftJoin('a.project', 'p')
            ->leftJoin('a.team', 't')
            ->leftJoin('t.members', 'tm')
            ->where('a.user = :user')
            ->orWhere('p.owner = :user')
            ->orWhere('tm.user = :user')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $timeline = [];
        foreach ($activities as $activity) {
            $date = $activity->getCreatedAt()->format('Y-m-d');
            if (!isset($timeline[$date])) {
                $timeline[$date] = [];
            }
            $timeline[$date][] = $activity;
        }

        return $timeline;
    }

    /**
     * Count activities for a project in the last X days
     */
    public function countRecentByProject(Project $project, int $days = 30): int
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.project = :project')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete old activity logs (for cleanup)
     */
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
