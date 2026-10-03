<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Quote;

/**
 * Small predicates on a quote's life: omnibase/marketplace's
 * (Base\Marketplace\Service\QuoteStatusGuard), kept under this name for the
 * code that uses it.
 */
final class QuoteStatusGuard
{
    /** Accepted, its pack made, not paid yet: going back to checkout is allowed. */
    public static function isAwaitingPayment(Quote $quote): bool
    {
        return \Base\Marketplace\Service\QuoteStatusGuard::isAwaitingPayment($quote);
    }
}
