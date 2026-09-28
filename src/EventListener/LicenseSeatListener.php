<?php

namespace Base\Forge\EventListener;

use Base\Forge\Entity\LicenseSeat;
use Base\Forge\Service\LicenseSeats;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * A seat saved from the back office tells its holder, as one given from
 * the owner's account does (LicenseSeats::give, which sends it itself:
 * the `notified` flag keeps it to one e-mail).
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: LicenseSeat::class)]
final class LicenseSeatListener
{
    public function __construct(private readonly LicenseSeats $seats)
    {
    }

    public function postPersist(LicenseSeat $seat): void
    {
        if (!$seat->isNotified()) {
            $this->seats->welcome($seat);
        }
    }
}
