<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    public function save(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeamMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find member by team and user
     */
    public function findByTeamAndUser(Team $team, User $user): ?TeamMember
    {
        return $this->findOneBy(['team' => $team, 'user' => $user]);
    }

    /**
     * Find all memberships for a user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.team', 't')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all members of a team
     */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.user', 'u')
            ->andWhere('m.team = :team')
            ->setParameter('team', $team)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if user is member of team
     */
    public function isMember(Team $team, User $user): bool
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.team = :team')
            ->andWhere('m.user = :user')
            ->setParameter('team', $team)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Count members in a team
     */
    public function countByTeam(Team $team): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
