<?php

namespace App\Repository;

use App\Entity\EnvironmentVariable;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EnvironmentVariable>
 */
class EnvironmentVariableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EnvironmentVariable::class);
    }

    /**
     * Find all environment variables for a project
     *
     * @return EnvironmentVariable[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->setParameter('project', $project)
            ->orderBy('e.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a specific environment variable by project and key
     */
    public function findOneByProjectAndKey(Project $project, string $key): ?EnvironmentVariable
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.key = :key')
            ->setParameter('project', $project)
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if a key already exists for a project
     */
    public function keyExists(Project $project, string $key, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.project = :project')
            ->andWhere('e.key = :key')
            ->setParameter('project', $project)
            ->setParameter('key', $key);

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :id')
                ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
