<?php

namespace Base\Forge\Repository;

use Base\Forge\Entity\Quote;
use Base\Marketplace\Quote\QuoteRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The studio's quotes, numbered Q-<year>-<n>: what every quote table answers
 * is omnibase/marketplace's (QuoteRepositoryTrait: saveNumbered(),
 * findForClient(), pipeline()).
 *
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    use QuoteRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }
}
