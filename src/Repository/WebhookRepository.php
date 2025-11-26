<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use App\Entity\Webhook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Webhook>
 */
class WebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webhook::class);
    }

    /**
     * @return Webhook[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->setParameter('user', $user)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Webhook[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Webhook[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.project = :project')
            ->setParameter('project', $project)
            ->orderBy('w.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find webhooks that should be triggered for a specific event
     * @return Webhook[]
     */
    public function findForEvent(string $event, ?Project $project = null, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.isActive = true');

        if ($project) {
            // Get project-specific webhooks OR user's global webhooks
            $qb->andWhere('(w.project = :project OR (w.project IS NULL AND w.user = :user))')
               ->setParameter('project', $project)
               ->setParameter('user', $project->getOwner());
        } elseif ($user) {
            $qb->andWhere('w.user = :user')
               ->setParameter('user', $user);
        }

        // Get all matching webhooks and filter by event in PHP
        // This is more portable across databases (MySQL, PostgreSQL, SQLite)
        $webhooks = $qb->getQuery()->getResult();

        return array_filter($webhooks, fn(Webhook $w) => $w->hasEvent($event));
    }

    /**
     * @return Webhook[]
     */
    public function findFailingWebhooks(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.isActive = true')
            ->andWhere('w.lastResponseCode IS NOT NULL')
            ->andWhere('w.lastResponseCode NOT BETWEEN 200 AND 299')
            ->setParameter('user', $user)
            ->orderBy('w.lastTriggeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
