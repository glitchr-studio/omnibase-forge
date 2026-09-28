<?php

namespace Tests\Base\Forge\Entity;

use Base\Forge\Entity\License;
use Base\Forge\Entity\LicenseSeat;
use Base\Forge\Entity\Release;
use Base\Forge\Entity\Software;
use Base\Forge\Enum\LicenseStatus;
use Base\Forge\Enum\Pricing;
use Tests\Base\Forge\ForgeKernelTestCase;

class LicenseTest extends ForgeKernelTestCase
{
    private function license(): License
    {
        $software = (new Software(null, 'Forge', 'forge'))->setPricing(Pricing::LICENSED);

        return new License($software, $this->user());
    }

    public function testOnlyTheHashOfASeatsKeyIsKept(): void
    {
        $seat = new LicenseSeat($this->license(), 'Someone@Example.org ');
        self::assertSame('someone@example.org', $seat->getEmail(), 'e-mails compared lower-cased');
        self::assertFalse($seat->hasKey());
        self::assertFalse($seat->matchesKey('anything'));

        $seat->setKey('glk_SECRET');
        self::assertTrue($seat->hasKey());
        self::assertTrue($seat->matchesKey('glk_SECRET'));
        self::assertFalse($seat->matchesKey('glk_SECRET2'));
        self::assertSame('glk_SECRET', $seat->getKeyPrefix(), 'the first 12 characters, to recognise it');
    }

    public function testItsSeatsAreCountedAndFoundByEmail(): void
    {
        $license = $this->license()->setSeats(2);
        self::assertSame(2, $license->getSeatsLeft());

        $license->addHolder(new LicenseSeat($license, 'one@example.org'));
        self::assertSame(1, $license->getSeatsTaken());
        self::assertSame(1, $license->getSeatsLeft());
        self::assertNotNull($license->findSeat(' ONE@example.org'));
        self::assertNull($license->findSeat('two@example.org'));

        $license->setSeats(0);
        self::assertSame(1, $license->getSeats(), 'never fewer seats than are given');
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
        $other = (new Release(new Software(null, 'Other', 'other'), '1.0.0'))->setPublishedAt(new \DateTimeImmutable('2025-01-01'));

        self::assertTrue($license->covers($old));
        self::assertTrue($license->covers($new));
        self::assertFalse($license->covers($other), 'another software');

        $license->setUpdatesUntil(new \DateTimeImmutable('2026-01-01'));
        self::assertTrue($license->covers($old));
        self::assertFalse($license->covers($new), 'released after its updates ended');
    }
}
