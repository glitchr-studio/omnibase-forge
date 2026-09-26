<?php

namespace Tests\Base\Forge\Service;

use Base\Forge\Service\LicenseKey;
use PHPUnit\Framework\TestCase;

class LicenseKeyTest extends TestCase
{
    public function testAKeyIsPrefixedGroupedAndUnique(): void
    {
        $keys = array_map(fn () => LicenseKey::generate(), range(1, 50));

        foreach ($keys as $key) {
            self::assertTrue(LicenseKey::looksValid($key), $key);
            self::assertStringStartsWith('glk_', $key);
            self::assertSame(4 + 8 * 4 + 7, \strlen($key));
        }
        self::assertCount(50, array_unique($keys));
    }

    public function testLookalikesAreRejected(): void
    {
        self::assertFalse(LicenseKey::looksValid(''));
        self::assertFalse(LicenseKey::looksValid('glk_ABCD'));
        // I, L, O, U are not in Crockford's alphabet.
        self::assertFalse(LicenseKey::looksValid('glk_IIII-0000-0000-0000-0000-0000-0000-0000'));
        self::assertFalse(LicenseKey::looksValid(strtolower(LicenseKey::generate())));
    }
}
