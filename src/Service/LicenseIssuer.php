<?php

namespace Base\Forge\Service;

use Base\Entity\User;
use Base\Forge\Entity\License;
use Base\Forge\Entity\Product\LicenseOffer;
use Base\Forge\Entity\Software;
use Doctrine\ORM\EntityManagerInterface;

/** Issues licences (from a paid offer, or by hand) and their keys. */
class LicenseIssuer
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** A licence as the offer describes it: its seats, its validity, its updates. Persisted, not flushed. */
    public function issue(Software $software, User $owner, ?LicenseOffer $offer = null, ?string $orderReference = null, ?\DateTimeImmutable $at = null): License
    {
        $at ??= new \DateTimeImmutable();

        $license = new License($software, $owner);
        $license->setOrderReference($orderReference);

        if ($offer) {
            $license->setSeats($offer->getSeats());
            if ($months = $offer->getDurationMonths()) {
                $license->setExpiresAt($at->modify(sprintf('+%d months', $months)));
            }
            if ($months = $offer->getUpdatesMonths()) {
                $license->setUpdatesUntil($at->modify(sprintf('+%d months', $months)));
            }
        }

        $this->entityManager->persist($license);

        return $license;
    }

    /**
     * A new key for the licence, replacing the old one (which stops working
     * at once). Returned in clear this once; only its hash is kept. Flushed.
     */
    public function rekey(License $license): string
    {
        $key = LicenseKey::generate();
        $license->setKey($key);
        $this->entityManager->flush();

        return $key;
    }
}
