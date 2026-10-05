<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Job;
use Base\Forge\Entity\Pipeline;

/**
 * A pipeline as a graph: a node per job, ranked by its stage, in the state of its status; an edge
 * from each job it waits for - the ones it names (`needs`), or every job of the stage before.
 * The shape is the one @glitchr/graphjs draws (its `pipeline` preset): given to setData() as it
 * is, or written in the page as data-graph-* attributes (templates/_pipeline.html.twig).
 */
final class PipelineGraph
{
    /**
     * @return array{nodes: list<array{id: string, label: string, sub: string, rank: int, state: string, href: ?string, title: string}>,
     *               edges: list<array{from: string, to: string}>}
     */
    public function data(Pipeline $pipeline): array
    {
        $nodes = $edges = [];
        $byName = [];
        foreach ($pipeline->getJobs() as $job) {
            $byName[$job->getName()] = self::id($job);
        }

        $previous = [];
        $rank = 0;
        foreach ($pipeline->getStages() as $stage) {
            $current = [];
            foreach ($stage->getJobs() as $job) {
                $id = self::id($job);
                $current[] = $id;
                $status = $job->getEffectiveStatus();
                $nodes[] = [
                    'id' => $id,
                    'label' => $job->getName(),
                    'sub' => $stage->getName(),
                    'rank' => $rank,
                    'state' => $status->value,
                    'href' => $job->getUrl(),
                    'title' => sprintf('%s · %s · %s', $stage->getName(), $job->getName(), $status->value),
                ];
                $from = array_values(array_filter(array_map(fn (string $need) => $byName[$need] ?? null, $job->getNeeds())));
                foreach ($from ?: $previous as $source) {
                    if ($source !== $id) {
                        $edges[] = ['from' => $source, 'to' => $id];
                    }
                }
            }
            // An empty stage does not break the chain.
            if ($current) {
                $previous = $current;
                ++$rank;
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /** @return array<string, list<string>> for each job's node, the nodes it comes from (data-graph-from) */
    public function sources(Pipeline $pipeline): array
    {
        $sources = [];
        foreach ($this->data($pipeline)['edges'] as $edge) {
            $sources[$edge['to']][] = $edge['from'];
        }

        return $sources;
    }

    /** A job's node: its id once saved, its place otherwise - never its name, which may hold anything. */
    public static function id(Job $job): string
    {
        return 'job-'.($job->getId() ?? spl_object_id($job));
    }
}
