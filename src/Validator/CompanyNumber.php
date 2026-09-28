<?php

namespace Base\Forge\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A SIREN or SIRET that exists: well formed, and known to the State's
 * register (CompanyRegistry). A register that does not answer lets it
 * through - the quote is then marked unchecked, not refused.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CompanyNumber extends Constraint
{
    public string $invalid = 'company.invalid';
    public string $unknown = 'company.unknown';
}
