<?php

namespace Tests\Base\Forge\Service;

use Base\Forge\Entity\Pipeline;
use Base\Forge\Enum\PipelineStatus;
use Base\Forge\Service\PipelineGraph;
use PHPUnit\Framework\TestCase;

class PipelineGraphTest extends TestCase
{
    public function testAJobIsANodeRankedByItsStageAndWaitsForTheStageBefore(): void
    {
        $pipeline = new Pipeline(null, 'main');
        $install = $pipeline->stage('install')->job('composer', PipelineStatus::SUCCESS);
        $unit = $pipeline->stage('test')->job('phpunit', PipelineStatus::RUNNING)->setUrl('https://ci.example.org/jobs/2');
        $lint = $pipeline->stage('test')->job('lint', PipelineStatus::FAILED)->setAllowFailure(true);
        $pipeline->stage('empty');
        $deploy = $pipeline->stage('deploy')->job('production', PipelineStatus::MANUAL);

        $graph = (new PipelineGraph())->data($pipeline);
        $id = fn ($job) => PipelineGraph::id($job);

        self::assertSame(
            [['composer', 'install', 0, 'success'], ['phpunit', 'test', 1, 'running'], ['lint', 'test', 1, 'warning'], ['production', 'deploy', 2, 'manual']],
            array_map(fn (array $node) => [$node['label'], $node['sub'], $node['rank'], $node['state']], $graph['nodes']),
            'an empty stage takes no rank; a failure that was allowed shows as a warning',
        );
        self::assertSame('https://ci.example.org/jobs/2', $graph['nodes'][1]['href']);
        self::assertNull($graph['nodes'][0]['href']);
        self::assertSame([
            ['from' => $id($install), 'to' => $id($unit)],
            ['from' => $id($install), 'to' => $id($lint)],
            ['from' => $id($unit), 'to' => $id($deploy)],
            ['from' => $id($lint), 'to' => $id($deploy)],
        ], $graph['edges'], 'each job waits for every job of the stage before - across an empty one');
        self::assertSame([$id($unit), $id($lint)], (new PipelineGraph())->sources($pipeline)[$id($deploy)]);
        self::assertCount(4, array_unique(array_column($graph['nodes'], 'id')), 'each node has its own id');
    }

    public function testAJobThatNamesWhatItNeedsWaitsForThoseOnly(): void
    {
        $pipeline = new Pipeline();
        $build = $pipeline->stage('build')->job('assets', PipelineStatus::SUCCESS);
        $unit = $pipeline->stage('test')->job('phpunit', PipelineStatus::SUCCESS);
        $pipeline->stage('test')->job('e2e', PipelineStatus::SUCCESS);
        $deploy = $pipeline->stage('deploy')->job('docs', PipelineStatus::PENDING)->setNeeds(['assets', 'nothing-by-that-name', 'assets']);

        $edges = (new PipelineGraph())->data($pipeline)['edges'];
        $toDeploy = array_values(array_filter($edges, fn (array $edge) => $edge['to'] === PipelineGraph::id($deploy)));

        self::assertSame([['from' => PipelineGraph::id($build), 'to' => PipelineGraph::id($deploy)]], $toDeploy, 'the job it names, once; an unknown name draws nothing');
        self::assertContains(['from' => PipelineGraph::id($build), 'to' => PipelineGraph::id($unit)], $edges);
        self::assertSame(['nodes' => [], 'edges' => []], (new PipelineGraph())->data(new Pipeline()));
    }
}
