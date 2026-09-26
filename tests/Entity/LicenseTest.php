<?php

namespace Tests\Base\Forge\Entity;

use Base\Entity\User;
use Base\Forge\Entity\License;
use Base\Forge\Entity\Release;
use Base\Forge\Entity\Software;
use Base\Forge\Enum\LicenseStatus;
use Base\Forge\Enum\Pricing;
use PHPUnit\Framework\TestCase;

class LicenseTest extends TestCase
{
    private function license(): License
    {
        $software = (new Software('Forge', 'forge'))->setPricing(Pricing::LICENSED);

        return new License($software, $this->createStub(User::class));
    }

    public function testOnlyTheHashOfTheKeyIsKept(): void
    {
        $license = $this->license();
        self::assertFalse($license->hasKey());
        self::assertFalse($license->matchesKey('anything'));

        $license->setKey('glk_SECRET');
        self::assertTrue($license->hasKey());
        self::assertTrue($license->matchesKey('glk_SECRET'));
        self::assertFalse($license->matchesKey('glk_SECRET2'));
        self::assertSame('glk_SECRET', $license->getKeyPrefix(), 'the first 12 characters, to recognise it');
    }

    public function testValidityFollowsStatusAndExpiry(): void
    {
        $license = $this->license();
        self::assertTrue($license->isValid());

        $license->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        self::assertFalse($license->isValid(), 'past its expiry, before any cron marks it');

        $license->setExpiresAt(new \DateTimeImmutable('+1 year'))->setStatus(LicenseStatus::REVOKED);
        self::assertFalse($license->isValid());
    }

    public function testItCoversTheReleasesWithinItsUpdates(): void
    {
        $license = $this->license();
        $software = $license->getSoftware();
        $old = (new Release($software, '1.0.0'))->setPublishedAt(new \DateTimeImmutable('2025-01-01'));
        $new = (new Release($software, '2.0.0'))->setPublishedAt(new \DateTimeImmutable('2026-06-01'));
        $other = (new Release(new Software('Other', 'other'), '1.0.0'))->setPublishedAt(new \DateTimeImmutable('2025-01-01'));

        self::assertTrue($license->covers($old));
        self::assertTrue($license->covers($new));
        self::assertFalse($license->covers($other), 'another software');

        $license->setUpdatesUntil(new \DateTimeImmutable('2026-01-01'));
        self::assertTrue($license->covers($old));
        self::assertFalse($license->covers($new), 'released after its updates ended');
    }
}
