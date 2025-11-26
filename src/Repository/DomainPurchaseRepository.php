<?php

namespace App\Repository;

use App\Entity\DomainPurchase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DomainPurchase>
 */
class DomainPurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DomainPurchase::class);
    }

    /**
     * Find all domain purchases by user
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.user = :user')
            ->setParameter('user', $user)
            ->orderBy('dp.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find completed domains by user
     */
    public function findCompletedByUser(User $user): array
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.user = :user')
            ->andWhere('dp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', DomainPurchase::STATUS_COMPLETED)
            ->orderBy('dp.domain', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find domains expiring soon
     */
    public function findExpiringSoon(int $days = 30): array
    {
        $expiryDate = new \DateTimeImmutable("+{$days} days");

        return $this->createQueryBuilder('dp')
            ->andWhere('dp.status = :status')
            ->andWhere('dp.expiresAt IS NOT NULL')
            ->andWhere('dp.expiresAt <= :expiryDate')
            ->andWhere('dp.expiresAt >= :now')
            ->setParameter('status', DomainPurchase::STATUS_COMPLETED)
            ->setParameter('expiryDate', $expiryDate)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('dp.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if domain is already owned by user
     */
    public function isDomainOwnedByUser(User $user, string $domain): bool
    {
        $result = $this->createQueryBuilder('dp')
            ->select('COUNT(dp.id)')
            ->andWhere('dp.user = :user')
            ->andWhere('dp.domain = :domain')
            ->andWhere('dp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('domain', strtolower($domain))
            ->setParameter('status', DomainPurchase::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Find by domain name
     */
    public function findByDomain(string $domain): ?DomainPurchase
    {
        return $this->createQueryBuilder('dp')
            ->andWhere('dp.domain = :domain')
            ->andWhere('dp.status = :status')
            ->setParameter('domain', strtolower($domain))
            ->setParameter('status', DomainPurchase::STATUS_COMPLETED)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
