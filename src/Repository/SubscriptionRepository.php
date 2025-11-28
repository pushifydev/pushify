<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Find active subscription for a user
     */
    public function findActiveByUser(User $user): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all subscriptions for a user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find subscriptions expiring soon (within X days)
     */
    public function findExpiringSoon(int $days = 3): array
    {
        $date = new \DateTime("+{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.currentPeriodEnd <= :date')
            ->andWhere('s.currentPeriodEnd > :now')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expired subscriptions that need to be marked as expired
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.currentPeriodEnd < :now')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as count, SUM(s.amount) as revenue')
            ->groupBy('s.status');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'active' => 0,
            'cancelled' => 0,
            'expired' => 0,
            'revenue' => 0,
        ];

        foreach ($results as $result) {
            $count = (int) $result['count'];
            $stats['total'] += $count;

            if ($result['status'] === Subscription::STATUS_ACTIVE) {
                $stats['active'] = $count;
                $stats['revenue'] = (float) $result['revenue'];
            } elseif ($result['status'] === Subscription::STATUS_CANCELLED) {
                $stats['cancelled'] = $count;
            } elseif ($result['status'] === Subscription::STATUS_EXPIRED) {
                $stats['expired'] = $count;
            }
        }

        return $stats;
    }
}
