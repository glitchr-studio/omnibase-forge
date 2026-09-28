<?php

namespace Base\Forge\Exception;

/** Why an activation is refused: a reason the software can act on, and its HTTP status. */
final class LicenseActivationException extends \RuntimeException
{
    public const INVALID_KEY = 'invalid_key';
    public const WRONG_SOFTWARE = 'wrong_software';
    public const NOT_VALID = 'license_not_valid';
    public const NO_MACHINE_LEFT = 'no_machine_left';
    public const NOT_CONFIGURED = 'not_configured';

    private const STATUS = [
        self::INVALID_KEY => 401,
        self::WRONG_SOFTWARE => 403,
        self::NOT_VALID => 403,
        self::NO_MACHINE_LEFT => 409,
        self::NOT_CONFIGURED => 503,
    ];

    /** @param array<string, mixed> $details */
    public function __construct(public readonly string $reason, public readonly array $details = [])
    {
        parent::__construct($reason);
    }

    public function getStatus(): int
    {
        return self::STATUS[$this->reason] ?? 400;
    }
}
