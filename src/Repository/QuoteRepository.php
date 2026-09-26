<?php

namespace Base\Forge\Repository;

use Base\Entity\User;
use Base\Forge\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Quote> */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /** Q-<year>-<n>, n counting the year's quotes from 1. */
    public function nextReference(?\DateTimeImmutable $at = null): string
    {
        $year = ($at ?? new \DateTimeImmutable())->format('Y');
        $count = (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.reference LIKE :prefix')->setParameter('prefix', 'Q-'.$year.'-%')
            ->getQuery()->getSingleScalarResult();

        return sprintf('Q-%s-%04d', $year, $count + 1);
    }

    /** @return list<Quote> the client's, or those sent to their e-mail */
    public function findForClient(User $user): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.client = :user OR q.email = :email')
            ->setParameter('user', $user)->setParameter('email', (string) $user->getEmail())
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
