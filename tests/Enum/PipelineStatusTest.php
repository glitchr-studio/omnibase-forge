<?php

namespace Tests\Base\Forge\Enum;

use Base\Forge\Entity\Pipeline;
use Base\Forge\Enum\PipelineStatus as S;
use PHPUnit\Framework\TestCase;

class PipelineStatusTest extends TestCase
{
    public function testAWholeIsWhatItsPartsSay(): void
    {
        self::assertSame(S::PENDING, S::of([]), 'nothing yet');
        self::assertSame(S::SUCCESS, S::of([S::SUCCESS, S::SUCCESS]));
        self::assertSame(S::FAILED, S::of([S::SUCCESS, S::FAILED, S::RUNNING]), 'one failure fails it, whatever still runs');
        self::assertSame(S::RUNNING, S::of([S::SUCCESS, S::RUNNING, S::PENDING]));
        self::assertSame(S::RUNNING, S::of([S::SUCCESS, S::PENDING]), 'some done, some not started: under way');
        self::assertSame(S::PENDING, S::of([S::PENDING, S::PENDING]));
        self::assertSame(S::PENDING, S::of([S::PENDING, S::MANUAL]));
        self::assertSame(S::MANUAL, S::of([S::SUCCESS, S::MANUAL]), 'waiting for someone');
        self::assertSame(S::WARNING, S::of([S::SUCCESS, S::WARNING]));
        self::assertSame(S::SUCCESS, S::of([S::SUCCESS, S::SKIPPED]));
        self::assertSame(S::SKIPPED, S::of([S::SKIPPED]));
        self::assertSame(S::CANCELED, S::of([S::SUCCESS, S::CANCELED]));
        self::assertTrue(S::FAILED->isFinished());
        self::assertFalse(S::MANUAL->isFinished());
    }

    public function testAPipelineIsWhatItsStagesSayUnlessSetByHand(): void
    {
        $pipeline = new Pipeline(null, 'main', str_repeat('a', 40));
        self::assertSame(S::PENDING, $pipeline->getStatus());
        self::assertSame('aaaaaaaa', $pipeline->getShortSha());

        $pipeline->stage('test')->job('phpunit', S::SUCCESS);
        $pipeline->stage('test')->job('lint', S::FAILED)->setAllowFailure(true);
        $pipeline->stage('deploy')->job('production', S::MANUAL);
        self::assertCount(2, $pipeline->getStages(), 'a stage is made once');
        self::assertSame([0, 1], $pipeline->getStages()->map(fn ($stage) => $stage->getPosition())->toArray());
        self::assertSame(S::WARNING, $pipeline->stage('test')->getStatus(), 'a failure that was allowed is a warning');
        self::assertSame(S::MANUAL, $pipeline->getStatus());

        $pipeline->stage('deploy')->job('production', S::SUCCESS);
        self::assertSame(S::WARNING, $pipeline->getStatus());
        self::assertCount(3, $pipeline->getJobs(), 'a job named again is brought up to date, not made twice');

        $pipeline->setStatus(S::CANCELED);
        self::assertSame(S::CANCELED, $pipeline->getStatus());

        $pipeline->setFinishedAt($pipeline->getCreatedAt()->modify('+95 seconds'));
        self::assertSame(95, $pipeline->getDuration());
    }
}
