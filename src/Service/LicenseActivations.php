<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\LicenseActivation;
use Base\Forge\Exception\LicenseActivationException;
use Base\Forge\Entity\LicenseSeat;
use Base\Forge\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The machines a seat's software runs on. The software sends its seat's
 * key and its machine's identifier: the first time, the machine takes one
 * of the seat's places (License::$machinesPerSeat); every time, it gets a
 * fresh signed token (LicenseToken) to run offline until the next. When
 * every place is taken it is told which machines hold them - its holder
 * frees one from their account, or from that machine (release()).
 */
class LicenseActivations
{
    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly LicenseToken $tokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Activates the machine - or finds it already activated - and signs it
     * a token. Flushed.
     *
     * @return array{token: string, payload: array<string, mixed>, activation: LicenseActivation, machines_left: int}
     *
     * @throws LicenseActivationException
     */
    public function activate(string $key, string $machineId, string $software, ?string $name = null, ?string $platform = null, ?string $version = null): array
    {
        if (!$this->tokens->isConfigured()) {
            throw new LicenseActivationException(LicenseActivationException::NOT_CONFIGURED);
        }
        $seat = $this->seat($key, $software);

        $machine = LicenseActivation::hash($machineId);
        $activation = $seat->findActivation($machine);
        if (!$activation) {
            if ($seat->getMachinesLeft() < 1) {
                throw new LicenseActivationException(LicenseActivationException::NO_MACHINE_LEFT, [
                    'machines' => array_map(fn (LicenseActivation $a) => [
                        'name' => $a->getName(),
                        'platform' => $a->getPlatform(),
                        'last_seen' => $a->getLastSeenAt()->format(\DATE_ATOM),
                    ], $seat->getActivations()->toArray()),
                ]);
            }
            $activation = new LicenseActivation($seat, $machine);
            $seat->addActivation($activation);
            $this->entityManager->persist($activation);
        }
        $activation->setName($name ?? $activation->getName())
            ->setPlatform($platform ?? $activation->getPlatform())
            ->setVersion($version ?? $activation->getVersion())
            ->seen();
        $this->entityManager->flush();

        return $this->tokens->issue($activation) + ['activation' => $activation, 'machines_left' => $seat->getMachinesLeft()];
    }

    /**
     * The software frees its own machine - before an uninstall, a move.
     * Flushed. False when that machine was not activated.
     *
     * @throws LicenseActivationException a key that is no seat's
     */
    public function releaseMachine(string $key, string $machineId): bool
    {
        $seat = $this->licenses->findSeatByKey($key) ?? throw new LicenseActivationException(LicenseActivationException::INVALID_KEY);
        $activation = $seat->findActivation(LicenseActivation::hash($machineId));
        if (!$activation) {
            return false;
        }
        $this->release($activation);

        return true;
    }

    /** Frees the machine's place: its token runs until it expires, then it must ask again. Flushed. */
    public function release(LicenseActivation $activation): void
    {
        $activation->getSeat()?->removeActivation($activation);
        $this->entityManager->remove($activation);
        $this->entityManager->flush();
    }

    /** The seat behind the key, on a valid licence of that software. */
    private function seat(string $key, string $software): LicenseSeat
    {
        $seat = LicenseKey::looksValid(trim($key)) ? $this->licenses->findSeatByKey(trim($key)) : null;
        if (!$seat) {
            throw new LicenseActivationException(LicenseActivationException::INVALID_KEY);
        }
        $license = $seat->getLicense();
        if ($license?->getSoftware()?->getSlug() !== $software) {
            throw new LicenseActivationException(LicenseActivationException::WRONG_SOFTWARE);
        }
        if (!$license->isValid()) {
            throw new LicenseActivationException(LicenseActivationException::NOT_VALID, ['status' => $license->getStatus()->value]);
        }

        return $seat;
    }
}
