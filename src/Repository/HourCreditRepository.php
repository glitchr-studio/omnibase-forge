<?php

namespace Base\Forge\Repository;

use Base\Entity\User;
use Base\Forge\Entity\HourCredit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HourCredit> */
class HourCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HourCredit::class);
    }

    /** Minutes left: everything bought minus everything logged. */
    public function balance(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.minutes), 0)')
            ->andWhere('c.user = :user')->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<HourCredit> newest first */
    public function history(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC')->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return array<int, int> user id => balance, for the users below $minutes who once bought hours */
    public function lowBalances(int $minutes): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.user) AS user, SUM(c.minutes) AS balance')
            ->groupBy('c.user')
            ->having('SUM(c.minutes) < :minutes')->setParameter('minutes', $minutes)
            ->getQuery()->getArrayResult();

        return array_column($rows, 'balance', 'user');
    }

    public function hasOrder(string $reference): bool
    {
        return null !== $this->findOneBy(['orderReference' => $reference]);
    }
}
