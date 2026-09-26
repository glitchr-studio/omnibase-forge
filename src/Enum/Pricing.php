<?php

namespace Base\Forge\Enum;

/** Whether a piece of software is downloaded freely or against a licence. */
enum Pricing: string
{
    case FREE = 'free';
    case LICENSED = 'licensed';

    public function isFree(): bool
    {
        return self::FREE === $this;
    }
}
