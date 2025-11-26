<?php

namespace App\Repository;

use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return Project[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByOwnerAndSlug(User $owner, string $slug): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->andWhere('p.slug = :slug')
            ->setParameter('owner', $owner)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByOwnerAndId(User $owner, int $id): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->andWhere('p.id = :id')
            ->setParameter('owner', $owner)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Project[]
     */
    public function findRecentByOwner(User $owner, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('p.id != :id')
                ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findByWebhookSecret(string $webhookSecret): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.webhookSecret = :secret')
            ->setParameter('secret', $webhookSecret)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
