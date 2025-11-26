<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamInvitation>
 */
class TeamInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamInvitation::class);
    }

    public function save(TeamInvitation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TeamInvitation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find invitation by token
     */
    public function findByToken(string $token): ?TeamInvitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find pending invitations for a team
     */
    public function findPendingByTeam(Team $team): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.team = :team')
            ->andWhere('i.status = :status')
            ->setParameter('team', $team)
            ->setParameter('status', TeamInvitation::STATUS_PENDING)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending invitations for a user's email
     */
    public function findPendingByEmail(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.email = :email')
            ->andWhere('i.status = :status')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('status', TeamInvitation::STATUS_PENDING)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if there's already a pending invitation for this email and team
     */
    public function hasPendingInvitation(Team $team, string $email): bool
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.team = :team')
            ->andWhere('i.email = :email')
            ->andWhere('i.status = :status')
            ->setParameter('team', $team)
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('status', TeamInvitation::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Find expired pending invitations
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.expiresAt < :now')
            ->setParameter('status', TeamInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark expired invitations
     */
    public function markExpiredInvitations(): int
    {
        return $this->createQueryBuilder('i')
            ->update()
            ->set('i.status', ':expiredStatus')
            ->andWhere('i.status = :pendingStatus')
            ->andWhere('i.expiresAt < :now')
            ->setParameter('expiredStatus', TeamInvitation::STATUS_EXPIRED)
            ->setParameter('pendingStatus', TeamInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Find all invitations for a team
     */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.team = :team')
            ->setParameter('team', $team)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
