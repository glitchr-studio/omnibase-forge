<?php

namespace Base\Forge\Repository;

use Base\Entity\User;
use Base\Forge\Entity\License;
use Base\Forge\Entity\Software;
use Base\Forge\Enum\LicenseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<License> */
class LicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, License::class);
    }

    /** @return list<License> newest first */
    public function findOwnedBy(User $user): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.software', 's')->addSelect('s')
            ->andWhere('l.owner = :user')->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<License> the user's valid licences for that software, best first */
    public function findValidFor(User $user, Software $software): array
    {
        $licenses = $this->createQueryBuilder('l')
            ->andWhere('l.owner = :user')->setParameter('user', $user)
            ->andWhere('l.software = :software')->setParameter('software', $software)
            ->andWhere('l.status = :active')->setParameter('active', LicenseStatus::ACTIVE)
            ->getQuery()->getResult();

        $licenses = array_values(array_filter($licenses, fn (License $license) => $license->isValid()));
        usort($licenses, fn (License $a, License $b) => ($b->getUpdatesUntil()?->getTimestamp() ?? PHP_INT_MAX) <=> ($a->getUpdatesUntil()?->getTimestamp() ?? PHP_INT_MAX));

        return $licenses;
    }

    public function findOneByKey(string $key): ?License
    {
        return $this->findOneBy(['keyHash' => hash('sha256', $key)]);
    }

    /** @return list<License> active ones whose expiry has passed */
    public function findLapsed(\DateTimeImmutable $at): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')->setParameter('active', LicenseStatus::ACTIVE)
            ->andWhere('l.expiresAt IS NOT NULL AND l.expiresAt <= :at')->setParameter('at', $at)
            ->getQuery()->getResult();
    }
}
