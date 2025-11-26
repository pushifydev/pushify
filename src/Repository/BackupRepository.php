<?php

namespace App\Repository;

use App\Entity\Backup;
use App\Entity\Database;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Backup>
 */
class BackupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Backup::class);
    }

    /**
     * Find backups by database
     */
    public function findByDatabase(Database $database): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.database = :database')
            ->setParameter('database', $database)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find backups by project
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.database', 'd')
            ->where('d.project = :project')
            ->setParameter('project', $project)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get backup statistics for a database
     */
    public function getDatabaseStats(Database $database): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select(
                'COUNT(b.id) as total',
                'SUM(CASE WHEN b.status = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN b.status = :failed THEN 1 ELSE 0 END) as failed',
                'SUM(CASE WHEN b.status = :creating THEN 1 ELSE 0 END) as in_progress',
                'SUM(b.fileSizeBytes) as total_size'
            )
            ->where('b.database = :database')
            ->setParameter('database', $database)
            ->setParameter('completed', Backup::STATUS_COMPLETED)
            ->setParameter('failed', Backup::STATUS_FAILED)
            ->setParameter('creating', Backup::STATUS_CREATING);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'completed' => (int)$result['completed'],
            'failed' => (int)$result['failed'],
            'in_progress' => (int)$result['in_progress'],
            'total_size_bytes' => (int)$result['total_size'] ?? 0,
            'total_size_mb' => round(((int)$result['total_size'] ?? 0) / 1024 / 1024, 2),
        ];
    }

    /**
     * Get backup statistics for a project
     */
    public function getProjectStats(Project $project): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select(
                'COUNT(b.id) as total',
                'SUM(CASE WHEN b.status = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN b.status = :failed THEN 1 ELSE 0 END) as failed',
                'SUM(CASE WHEN b.status = :creating THEN 1 ELSE 0 END) as in_progress',
                'SUM(b.fileSizeBytes) as total_size'
            )
            ->join('b.database', 'd')
            ->where('d.project = :project')
            ->setParameter('project', $project)
            ->setParameter('completed', Backup::STATUS_COMPLETED)
            ->setParameter('failed', Backup::STATUS_FAILED)
            ->setParameter('creating', Backup::STATUS_CREATING);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int)$result['total'],
            'completed' => (int)$result['completed'],
            'failed' => (int)$result['failed'],
            'in_progress' => (int)$result['in_progress'],
            'total_size_bytes' => (int)$result['total_size'] ?? 0,
            'total_size_mb' => round(((int)$result['total_size'] ?? 0) / 1024 / 1024, 2),
        ];
    }

    /**
     * Find expired backups that should be deleted
     */
    public function findExpiredBackups(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('b')
            ->where('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt < :now')
            ->andWhere('b.status = :completed')
            ->setParameter('now', $now)
            ->setParameter('completed', Backup::STATUS_COMPLETED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent backups (last N days)
     */
    public function findRecentBackups(int $days = 7, ?Project $project = null): array
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        $qb = $this->createQueryBuilder('b')
            ->where('b.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('b.createdAt', 'DESC');

        if ($project) {
            $qb->join('b.database', 'd')
                ->andWhere('d.project = :project')
                ->setParameter('project', $project);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find backups by type
     */
    public function findByType(string $type, ?Database $database = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.type = :type')
            ->setParameter('type', $type)
            ->orderBy('b.createdAt', 'DESC');

        if ($database) {
            $qb->andWhere('b.database = :database')
                ->setParameter('database', $database);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find latest successful backup for a database
     */
    public function findLatestSuccessfulBackup(Database $database): ?Backup
    {
        return $this->createQueryBuilder('b')
            ->where('b.database = :database')
            ->andWhere('b.status = :completed')
            ->setParameter('database', $database)
            ->setParameter('completed', Backup::STATUS_COMPLETED)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count backups by status
     */
    public function countByStatus(string $status, ?Database $database = null): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.status = :status')
            ->setParameter('status', $status);

        if ($database) {
            $qb->andWhere('b.database = :database')
                ->setParameter('database', $database);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get total storage used by backups
     */
    public function getTotalStorageUsed(?Database $database = null): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('SUM(b.fileSizeBytes)')
            ->where('b.status = :completed')
            ->setParameter('completed', Backup::STATUS_COMPLETED);

        if ($database) {
            $qb->andWhere('b.database = :database')
                ->setParameter('database', $database);
        }

        return (int)$qb->getQuery()->getSingleScalarResult() ?? 0;
    }

    /**
     * Find backups for cleanup (old, completed backups)
     */
    public function findBackupsForCleanup(int $keepLast = 10): array
    {
        // Keep at least the last N backups per database
        $allBackups = $this->createQueryBuilder('b')
            ->where('b.status = :completed')
            ->setParameter('completed', Backup::STATUS_COMPLETED)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Group by database and keep only old ones
        $backupsByDatabase = [];
        foreach ($allBackups as $backup) {
            $dbId = $backup->getDatabase()->getId();
            if (!isset($backupsByDatabase[$dbId])) {
                $backupsByDatabase[$dbId] = [];
            }
            $backupsByDatabase[$dbId][] = $backup;
        }

        $backupsToDelete = [];
        foreach ($backupsByDatabase as $dbBackups) {
            if (count($dbBackups) > $keepLast) {
                $backupsToDelete = array_merge(
                    $backupsToDelete,
                    array_slice($dbBackups, $keepLast)
                );
            }
        }

        return $backupsToDelete;
    }
}
