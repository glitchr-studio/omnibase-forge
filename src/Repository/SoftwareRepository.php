<?php

namespace Base\Forge\Repository;

use Base\Forge\Entity\Software;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Software> */
class SoftwareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Software::class);
    }

    /** @return list<Software> what the applications page shows, in its order */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.visible = true')
            ->orderBy('s.position', 'ASC')->addOrderBy('s.name', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<Software> the ones with something to download */
    public function findDownloadable(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.releases', 'r')->addSelect('r')
            ->andWhere('s.visible = true')
            ->andWhere('r.publishedAt IS NOT NULL AND r.publishedAt <= CURRENT_TIMESTAMP()')
            ->orderBy('s.position', 'ASC')->addOrderBy('s.name', 'ASC')->addOrderBy('r.publishedAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Software> the ones git/git-bundle can build releases for */
    public function findWithRepository(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.repository IS NOT NULL')
            ->getQuery()->getResult();
    }

    /** @return list<Software> PHP packages served by the Composer repository */
    public function findPackages(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.packageName IS NOT NULL')
            ->orderBy('s.packageName', 'ASC')
            ->getQuery()->getResult();
    }
}
