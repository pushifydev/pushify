<?php

namespace App\Repository;

use App\Entity\Server;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    /**
     * @return Server[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Server[]
     */
    public function findActiveByOwner(User $owner): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :owner')
            ->andWhere('s.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', Server::STATUS_ACTIVE)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.owner = :owner')
            ->andWhere('s.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', Server::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByOwnerAndId(User $owner, int $id): ?Server
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :owner')
            ->andWhere('s.id = :id')
            ->setParameter('owner', $owner)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
