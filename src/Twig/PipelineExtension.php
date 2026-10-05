<?php

namespace Base\Forge\Twig;

use Base\Forge\Entity\Pipeline;
use Base\Forge\Entity\Project;
use Base\Forge\Repository\PipelineRepository;
use Base\Forge\Service\PipelineGraph;
use Twig\Attribute\AsTwigFunction;

/**
 * What the pipelines' templates ask: a project's latest runs, and a run as a graph
 * (Base\Forge\Service\PipelineGraph).
 */
final class PipelineExtension
{
    public function __construct(
        private readonly PipelineRepository $pipelines,
        private readonly PipelineGraph $graph,
    ) {
    }

    /** @return list<Pipeline> newest first */
    #[AsTwigFunction('forge_pipelines')]
    public function pipelines(Project $project, int $limit = 5): array
    {
        return $project->getId() ? $this->pipelines->findLatestFor($project, $limit) : [];
    }

    /** @return array{nodes: list<array>, edges: list<array>} for Graph.setData() */
    #[AsTwigFunction('forge_pipeline_graph')]
    public function graph(Pipeline $pipeline): array
    {
        return $this->graph->data($pipeline);
    }

    /** @return array<string, list<string>> node id -> the nodes it comes from */
    #[AsTwigFunction('forge_pipeline_sources')]
    public function sources(Pipeline $pipeline): array
    {
        return $this->graph->sources($pipeline);
    }
}
