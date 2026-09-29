<?php

namespace Base\Forge\Repository;

use Base\Entity\User;
use Base\Forge\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Quote> */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Numbers a new quote Q-<year>-<n> and saves it, in one transaction.
     *
     * n is one more than the year's highest, read with its rows locked (SELECT
     * ... FOR UPDATE): two quotes asked for at the same moment wait for each
     * other instead of taking the same number, and a quote deleted leaves a
     * gap rather than a number taken twice - counting the year's quotes (as
     * it used to) gave the next one an existing reference after any deletion,
     * and every quote after that failed on the unique reference.
     *
     * A quote that has its reference already is only saved.
     */
    public function saveNumbered(Quote $quote, ?\DateTimeImmutable $at = null): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->beginTransaction();
        try {
            if ('' === $quote->getReference()) {
                $year = ($at ?? new \DateTimeImmutable())->format('Y');
                $last = $this->createQueryBuilder('q')
                    ->select('q.reference')
                    ->andWhere('q.reference LIKE :prefix')->setParameter('prefix', 'Q-'.$year.'-%')
                    ->orderBy('q.reference', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
                $number = $last ? (int) substr((string) $last['reference'], \strlen('Q-'.$year.'-')) : 0;
                $quote->setReference(sprintf('Q-%s-%04d', $year, $number + 1));
            }
            $entityManager->persist($quote);
            $entityManager->flush();
            $entityManager->commit();
        } catch (\Throwable $e) {
            $entityManager->rollback();

            throw $e;
        }
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
