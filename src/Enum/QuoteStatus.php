<?php

namespace Base\Forge\Enum;

/*
 * A quote's statuses are omnibase/marketplace's now (Base\Marketplace\Enum\
 * QuoteStatus: requested, draft, sent, accepted, paid, declined), the same
 * for every trade. This name is kept for the code that uses it.
 */
class_alias(\Base\Marketplace\Enum\QuoteStatus::class, __NAMESPACE__.'\QuoteStatus');
