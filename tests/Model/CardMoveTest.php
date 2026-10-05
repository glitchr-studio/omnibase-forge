<?php

namespace Tests\Base\Forge\Model;

use Base\Forge\Enum\CardColumn;
use Base\Forge\Model\CardMove;
use PHPUnit\Framework\TestCase;

class CardMoveTest extends TestCase
{
    public function testItNamesTheColumnsOfTheBoardInOrder(): void
    {
        self::assertSame(['backlog', 'progress', 'review', 'done'], CardMove::columns());
        self::assertSame(CardColumn::REVIEW, (new CardMove('review', 2))->getColumn());
    }
}
