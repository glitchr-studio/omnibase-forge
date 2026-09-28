<?php

namespace Base\Forge\Repository;

use Base\Forge\Entity\ProjectReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProjectReview> */
class ProjectReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectReview::class);
    }

    /** @return list<ProjectReview> the ones the studio shows, newest first */
    public function findPublished(int $limit = 12): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.project', 'p')->addSelect('p')
            ->andWhere('r.published = true AND r.submittedAt IS NOT NULL')
            ->orderBy('r.submittedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
