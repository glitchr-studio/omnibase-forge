<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Quote;
use Base\Forge\Enum\QuoteStatus;

/** Small predicates on a quote's life the controllers and the voter share. */
final class QuoteStatusGuard
{
    /** Accepted, its pack made, not paid yet: going back to checkout is allowed. */
    public static function isAwaitingPayment(Quote $quote): bool
    {
        return QuoteStatus::ACCEPTED === $quote->getStatus() && null !== $quote->getProduct();
    }
}
