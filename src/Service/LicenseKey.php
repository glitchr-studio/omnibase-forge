<?php

namespace Base\Forge\Service;

/**
 * Licence keys: "glk_" and 32 characters of Crockford base32 (160 bits),
 * grouped by four for reading aloud. The prefix makes a leaked key easy to
 * recognise, and the Composer repository accepts it as the password.
 */
final class LicenseKey
{
    public const PREFIX = 'glk_';
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(\ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $key = '';
        foreach (str_split($bits, 5) as $chunk) {
            $key .= self::ALPHABET[bindec($chunk)];
        }

        return self::PREFIX.implode('-', str_split($key, 4));
    }

    public static function looksValid(string $key): bool
    {
        return 1 === preg_match('/^glk_([0-9A-HJKMNP-TV-Z]{4}-){7}[0-9A-HJKMNP-TV-Z]{4}$/', $key);
    }
}
