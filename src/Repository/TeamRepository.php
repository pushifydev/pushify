<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function save(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find teams owned by a user
     */
    public function findOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find teams where user is a member (including owned)
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.members', 'm')
            ->andWhere('t.owner = :user OR m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find team by slug
     */
    public function findBySlug(string $slug): ?Team
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find team by slug where user has access
     */
    public function findBySlugAndUser(string $slug, User $user): ?Team
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.members', 'm')
            ->andWhere('t.slug = :slug')
            ->andWhere('t.owner = :user OR m.user = :user')
            ->setParameter('slug', $slug)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if slug is unique
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('t.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() === 0;
    }
}
