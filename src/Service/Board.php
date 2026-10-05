<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Card;
use Base\Forge\Entity\Project;
use Base\Forge\Enum\CardColumn;

/**
 * A project's board, as it is moved: a card put at a place of a column, and the columns it
 * touched renumbered from zero - so the positions always read 0, 1, 2... with no gap and no tie,
 * whatever was dragged where. Nothing is saved here: the caller flushes.
 */
final class Board
{
    /**
     * Put the card at $position of $column (past the end: last) and renumber the column it
     * arrives in, and the one it left when it changed.
     */
    public function move(Card $card, CardColumn $column, int $position): void
    {
        $project = $card->getProject();
        if (!$project instanceof Project) {
            $card->moveTo($column, $position);

            return;
        }

        $from = $card->getColumn();
        $board = $project->getBoard();

        $target = array_values(array_filter($board[$column->value], fn (Card $each) => $each !== $card));
        array_splice($target, max(0, min($position, \count($target))), 0, [$card]);
        foreach ($target as $index => $each) {
            // moveTo() stamps the card: only the one that was dragged, and the ones it pushed.
            if ($each === $card || $each->getPosition() !== $index) {
                $each->moveTo($column, $index);
            }
        }

        if ($from !== $column) {
            foreach (array_values(array_filter($board[$from->value], fn (Card $each) => $each !== $card)) as $index => $each) {
                $each->setPosition($index);
            }
        }
    }

    /** A new card at the end of its column. */
    public function add(Project $project, Card $card): Card
    {
        $card->setPosition(\count($project->getBoard()[$card->getColumn()->value]));
        $project->addCard($card);

        return $card;
    }
}
