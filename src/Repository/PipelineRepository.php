<?php

namespace Base\Forge\Repository;

use Base\Forge\Entity\Pipeline;
use Base\Forge\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Pipeline> */
class PipelineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pipeline::class);
    }

    /** @return list<Pipeline> a project's latest runs, newest first */
    public function findLatestFor(Project $project, int $limit = 5): array
    {
        return $this->findBy(['project' => $project], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return list<Pipeline> the latest runs of every project, newest first */
    public function findLatest(int $limit = 30): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** The run a source calls by that id: the one to bring up to date rather than record twice. */
    public function findOneBySource(string $source, string $externalId): ?Pipeline
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }
}
