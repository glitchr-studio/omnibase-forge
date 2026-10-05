<?php

namespace Base\Forge\Model;

use Base\Forge\Enum\CardColumn;
use Symfony\Component\Validator\Constraints as Assert;

/** A drop on a project's board: which column, at which position (BoardController, #[MapRequestPayload]). */
final class CardMove
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'columns'])]
        public readonly string $column = '',
        #[Assert\PositiveOrZero]
        public readonly int $position = 0,
    ) {
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return array_map(fn (CardColumn $column) => $column->value, CardColumn::cases());
    }

    public function getColumn(): CardColumn
    {
        return CardColumn::from($this->column);
    }
}
