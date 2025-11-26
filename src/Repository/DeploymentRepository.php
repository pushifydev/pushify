<?php

namespace App\Repository;

use App\Entity\Deployment;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deployment>
 */
class DeploymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deployment::class);
    }

    /**
     * @return Deployment[]
     */
    public function findByProject(Project $project, int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLatestByProject(Project $project): ?Deployment
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestSuccessfulByProject(Project $project): ?Deployment
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', Deployment::STATUS_SUCCESS)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Deployment[]
     */
    public function findRunning(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [
                Deployment::STATUS_QUEUED,
                Deployment::STATUS_BUILDING,
                Deployment::STATUS_DEPLOYING,
            ])
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Deployment[]
     */
    public function findQueued(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', Deployment::STATUS_QUEUED)
            ->orderBy('d.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByProjectAndStatus(Project $project, string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.project = :project')
            ->andWhere('d.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find the current production deployment for a project
     */
    public function findCurrentProduction(Project $project): ?Deployment
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.isCurrentProduction = true')
            ->setParameter('project', $project)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clear current production flag for all deployments of a project
     */
    public function clearCurrentProduction(Project $project): void
    {
        $this->createQueryBuilder('d')
            ->update()
            ->set('d.isCurrentProduction', 'false')
            ->where('d.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();
    }

    /**
     * Find successful deployments that can be rolled back to
     * @return Deployment[]
     */
    public function findRollbackTargets(Project $project, int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.status = :status')
            ->andWhere('d.dockerImage IS NOT NULL')
            ->andWhere('d.dockerTag IS NOT NULL')
            ->setParameter('project', $project)
            ->setParameter('status', Deployment::STATUS_SUCCESS)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
