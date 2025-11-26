<?php

namespace App\Repository;

use App\Entity\PricingPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PricingPlan>
 */
class PricingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PricingPlan::class);
    }

    /**
     * @return PricingPlan[] Returns an array of active PricingPlan objects
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PricingPlan[] Returns an array of active PricingPlan objects by billing cycle
     */
    public function findActiveByBillingCycle(string $cycle): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.billingCycle = :cycle')
            ->setParameter('active', true)
            ->setParameter('cycle', $cycle)
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
