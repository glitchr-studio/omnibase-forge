<?php

namespace Tests\Base\Forge\Service;

use Base\Forge\Entity\Card;
use Base\Forge\Entity\Project;
use Base\Forge\Enum\CardColumn;
use Base\Forge\Service\Board;
use Tests\Base\Forge\ForgeKernelTestCase;

class BoardTest extends ForgeKernelTestCase
{
    /** @return array{0: Project, 1: array<string, Card>} a board: a, b, c to do; d in progress */
    private function board(): array
    {
        $project = new Project($this->user(), 'Site');
        $board = new Board();
        $cards = [];
        foreach (['a' => CardColumn::BACKLOG, 'b' => CardColumn::BACKLOG, 'c' => CardColumn::BACKLOG, 'd' => CardColumn::PROGRESS] as $title => $column) {
            $cards[$title] = $board->add($project, new Card($title, $column));
        }

        return [$project, $cards];
    }

    /** @return array<string, list<string>> the titles, column by column, in position order */
    private function titles(Project $project): array
    {
        $titles = [];
        foreach (CardColumn::cases() as $column) {
            $cards = $project->getCards()->filter(fn (Card $card) => $card->getColumn() === $column)->toArray();
            usort($cards, fn (Card $a, Card $b) => $a->getPosition() <=> $b->getPosition());
            $titles[$column->value] = array_map('strval', $cards);
            self::assertSame(array_keys($cards), array_map(fn (Card $card) => $card->getPosition(), $cards), 'positions run 0, 1, 2... without a gap or a tie');
        }

        return $titles;
    }

    public function testANewCardGoesToTheEndOfItsColumn(): void
    {
        [$project, $cards] = $this->board();

        self::assertSame([0, 1, 2, 0], array_map(fn (Card $card) => $card->getPosition(), array_values($cards)));
        self::assertSame(['a', 'b', 'c'], $this->titles($project)['backlog']);
    }

    public function testACardMovesWithinItsColumn(): void
    {
        [$project, $cards] = $this->board();

        (new Board())->move($cards['c'], CardColumn::BACKLOG, 0);
        self::assertSame(['c', 'a', 'b'], $this->titles($project)['backlog']);

        (new Board())->move($cards['c'], CardColumn::BACKLOG, 1);
        self::assertSame(['a', 'c', 'b'], $this->titles($project)['backlog']);
    }

    public function testACardChangesColumnAndBothAreRenumbered(): void
    {
        [$project, $cards] = $this->board();

        (new Board())->move($cards['a'], CardColumn::PROGRESS, 0);

        $titles = $this->titles($project);
        self::assertSame(['b', 'c'], $titles['backlog'], 'the column it left closes the gap');
        self::assertSame(['a', 'd'], $titles['progress']);
        self::assertSame(CardColumn::PROGRESS, $cards['a']->getColumn());
    }

    public function testAPositionPastTheEndIsTheEnd(): void
    {
        [$project, $cards] = $this->board();

        (new Board())->move($cards['b'], CardColumn::DONE, 12);
        self::assertSame(['b'], $this->titles($project)['done']);
        self::assertSame(0, $cards['b']->getPosition());

        (new Board())->move($cards['a'], CardColumn::PROGRESS, 99);
        self::assertSame(['d', 'a'], $this->titles($project)['progress']);
    }

    public function testOnlyTheCardsThatMovedAreStamped(): void
    {
        [, $cards] = $this->board();
        $before = $cards['a']->getUpdatedAt();
        usleep(2000);

        (new Board())->move($cards['c'], CardColumn::BACKLOG, 2);
        self::assertSame($before, $cards['a']->getUpdatedAt(), 'a card left where it was is not touched');

        (new Board())->move($cards['c'], CardColumn::BACKLOG, 0);
        self::assertNotSame($before, $cards['a']->getUpdatedAt(), 'a card pushed down is');
    }

    public function testACardWithoutAProjectIsSimplyPlaced(): void
    {
        $card = new Card('alone');
        (new Board())->move($card, CardColumn::REVIEW, 3);

        self::assertSame(CardColumn::REVIEW, $card->getColumn());
        self::assertSame(3, $card->getPosition());
    }
}
