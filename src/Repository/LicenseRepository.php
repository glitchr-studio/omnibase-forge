<?php

namespace Base\Forge\Repository;

use Base\Entity\User;
use Base\Forge\Entity\License;
use Base\Forge\Entity\LicenseSeat;
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

    /**
     * @return list<License> the licences a seat of which that user holds -
     *                       linked to them, or given to their e-mail once verified
     */
    public function findHeldBy(User $user, ?Software $software = null): array
    {
        $query = $this->createQueryBuilder('l')
            ->innerJoin('l.holders', 'h')
            ->innerJoin('l.software', 's')->addSelect('s')
            ->andWhere('h.user = :user OR (h.user IS NULL AND h.email = :email AND :verified = true)')
            ->setParameter('user', $user)
            ->setParameter('email', mb_strtolower((string) $user->getEmail()))
            ->setParameter('verified', $user->isVerified())
            ->orderBy('l.createdAt', 'DESC');
        if ($software) {
            $query->andWhere('l.software = :software')->setParameter('software', $software);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<License> the user's valid licences for that software - a seat of which they hold -, best first */
    public function findValidFor(User $user, Software $software): array
    {
        $licenses = array_values(array_filter($this->findHeldBy($user, $software), fn (License $license) => $license->isValid()));
        usort($licenses, fn (License $a, License $b) => ($b->getUpdatesUntil()?->getTimestamp() ?? PHP_INT_MAX) <=> ($a->getUpdatesUntil()?->getTimestamp() ?? PHP_INT_MAX));

        return $licenses;
    }

    /** The seat a key was made for. */
    public function findSeatByKey(string $key): ?LicenseSeat
    {
        return $this->getEntityManager()->getRepository(LicenseSeat::class)->findOneBy(['keyHash' => hash('sha256', $key)]);
    }

    /** @return list<License> the valid licences a seat of which is given to that e-mail */
    public function findValidByEmail(string $email): array
    {
        $licenses = $this->createQueryBuilder('l')
            ->innerJoin('l.holders', 'h')
            ->innerJoin('l.software', 's')->addSelect('s')
            ->andWhere('h.email = :email')->setParameter('email', mb_strtolower(trim($email)))
            ->andWhere('l.status = :active')->setParameter('active', LicenseStatus::ACTIVE)
            ->getQuery()->getResult();

        return array_values(array_filter($licenses, fn (License $license) => $license->isValid()));
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
