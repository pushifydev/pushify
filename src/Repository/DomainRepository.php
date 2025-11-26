<?php

namespace App\Repository;

use App\Entity\Domain;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Domain>
 */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    /**
     * @return Domain[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('d.isPrimary', 'DESC')
            ->addOrderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPrimaryByProject(Project $project): ?Domain
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.project = :project')
            ->andWhere('d.isPrimary = true')
            ->setParameter('project', $project)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDomainName(string $domain): ?Domain
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.domain = :domain')
            ->setParameter('domain', strtolower($domain))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find domains that need SSL renewal (expiring within 30 days)
     * @return Domain[]
     */
    public function findDomainsNeedingSslRenewal(): array
    {
        $thirtyDaysFromNow = new \DateTimeImmutable('+30 days');

        return $this->createQueryBuilder('d')
            ->andWhere('d.sslEnabled = true')
            ->andWhere('d.sslExpiresAt <= :expiryDate')
            ->setParameter('expiryDate', $thirtyDaysFromNow)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find domains pending DNS verification
     * @return Domain[]
     */
    public function findPendingVerification(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [Domain::STATUS_PENDING, Domain::STATUS_VERIFYING])
            ->getQuery()
            ->getResult();
    }

    /**
     * Find domains ready for SSL issuance
     * @return Domain[]
     */
    public function findReadyForSsl(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->andWhere('d.dnsVerified = true')
            ->andWhere('d.sslEnabled = false')
            ->setParameter('status', Domain::STATUS_VERIFIED)
            ->getQuery()
            ->getResult();
    }
}
