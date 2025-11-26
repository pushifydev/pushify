<?php

namespace App\Repository;

use App\Entity\AlertRule;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertRule>
 */
class AlertRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }

    /**
     * Get all enabled alert rules for a project
     *
     * @return AlertRule[]
     */
    public function findEnabledForProject(Project $project): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.project = :project')
            ->andWhere('r.enabled = true')
            ->setParameter('project', $project)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all alert rules for a project
     *
     * @return AlertRule[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.project = :project')
            ->setParameter('project', $project)
            ->orderBy('r.enabled', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
