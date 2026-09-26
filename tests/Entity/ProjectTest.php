<?php

namespace Tests\Base\Forge\Entity;

use Base\Forge\Entity\Card;
use Base\Forge\Entity\Project;
use Base\Forge\Entity\TimeEntry;
use Base\Forge\Enum\CardColumn;
use Tests\Base\Forge\ForgeKernelTestCase;

class ProjectTest extends ForgeKernelTestCase
{
    public function testTheBoardListsEveryColumnInOrder(): void
    {
        $project = new Project($this->user(), 'Site');
        $project->addCard(new Card('Logo', CardColumn::DONE));
        $project->addCard(new Card('Menu', CardColumn::BACKLOG));

        $board = $project->getBoard();
        self::assertSame(['backlog', 'progress', 'review', 'done'], array_keys($board));
        self::assertSame('Menu', (string) $board['backlog'][0]);
        self::assertSame([], $board['progress']);
    }

    public function testProgressIsTheShareOfDoneCardsElseOfTheBudget(): void
    {
        $project = new Project($this->user(), 'Site');
        $project->setBudgetMinutes(600);
        $project->addTimeEntry(new TimeEntry(150, 'Work'));
        self::assertSame(25, $project->getProgress(), 'no cards: the budget spent');

        $project->addCard(new Card('A', CardColumn::DONE));
        $project->addCard(new Card('B', CardColumn::DONE));
        $project->addCard(new Card('C', CardColumn::REVIEW));
        self::assertSame(67, $project->getProgress());
        self::assertSame(150, $project->getSpentMinutes());
    }
}
