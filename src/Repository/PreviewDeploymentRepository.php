<?php

namespace App\Repository;

use App\Entity\PreviewDeployment;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreviewDeployment>
 */
class PreviewDeploymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreviewDeployment::class);
    }

    public function save(PreviewDeployment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PreviewDeployment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all preview deployments for a project
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.project = :project')
            ->setParameter('project', $project)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active preview deployments for a project
     */
    public function findActiveByProject(Project $project): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.project = :project')
            ->andWhere('p.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', PreviewDeployment::STATUS_ACTIVE)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a preview deployment by project and PR number
     */
    public function findByProjectAndPr(Project $project, int $prNumber): ?PreviewDeployment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.project = :project')
            ->andWhere('p.prNumber = :prNumber')
            ->andWhere('p.status != :destroyed')
            ->setParameter('project', $project)
            ->setParameter('prNumber', $prNumber)
            ->setParameter('destroyed', PreviewDeployment::STATUS_DESTROYED)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all preview deployments for a user's projects
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.project', 'proj')
            ->andWhere('proj.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find preview deployments that are currently running (building/deploying)
     */
    public function findRunning(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', [
                PreviewDeployment::STATUS_PENDING,
                PreviewDeployment::STATUS_BUILDING,
                PreviewDeployment::STATUS_DEPLOYING,
            ])
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find stale preview deployments (active but not updated in X days)
     */
    public function findStale(int $days = 7): array
    {
        $cutoff = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.updatedAt < :cutoff OR (p.updatedAt IS NULL AND p.createdAt < :cutoff)')
            ->setParameter('status', PreviewDeployment::STATUS_ACTIVE)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active previews for a project
     */
    public function countActiveByProject(Project $project): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.project = :project')
            ->andWhere('p.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', PreviewDeployment::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get statistics for a project
     */
    public function getProjectStats(Project $project): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->andWhere('p.project = :project')
            ->setParameter('project', $project)
            ->groupBy('p.status');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'active' => 0,
            'failed' => 0,
            'destroyed' => 0,
        ];

        foreach ($results as $row) {
            $stats['total'] += $row['count'];
            if ($row['status'] === PreviewDeployment::STATUS_ACTIVE) {
                $stats['active'] = $row['count'];
            } elseif ($row['status'] === PreviewDeployment::STATUS_FAILED) {
                $stats['failed'] = $row['count'];
            } elseif ($row['status'] === PreviewDeployment::STATUS_DESTROYED) {
                $stats['destroyed'] = $row['count'];
            }
        }

        return $stats;
    }
}
