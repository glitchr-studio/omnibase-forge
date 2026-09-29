<?php

namespace Base\Forge\EventSubscriber;

use Base\Forge\Entity\HourCredit;
use Base\Forge\Entity\TimeEntry;
use Base\Forge\Service\HourLedger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Keeps every time entry's debit line in step with it, whoever writes the
 * entry (the back office, a command, a fixture): logged, changed or made
 * non-billable, the client's balance follows in the same flush. A deleted
 * entry takes its line with it (ON DELETE CASCADE).
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class TimeEntryLedgerSubscriber
{
    public function __construct(private readonly HourLedger $ledger)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(HourCredit::class);

        $entries = array_filter(
            [...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()],
            fn (object $entity) => $entity instanceof TimeEntry,
        );

        foreach ($entries as $entry) {
            $existing = $entry->getId() ? $entityManager->getRepository(HourCredit::class)->findOneBy(['timeEntry' => $entry]) : null;
            // Moved to another client's project: its debit moves with it - it
            // stayed on the old client, and the new one was never charged.
            $client = $entry->getProject()?->getClient();
            if ($existing && $client && $existing->getUser() !== $client) {
                $existing->setUser($client);
            }
            $credit = $this->ledger->debitFor($entry, $existing);

            if ($credit) {
                if (!$existing) {
                    $entityManager->persist($credit);
                    $unitOfWork->computeChangeSet($metadata, $credit);
                } else {
                    $unitOfWork->recomputeSingleEntityChangeSet($metadata, $credit);
                }
            } elseif ($existing) {
                $unitOfWork->scheduleForDelete($existing);
            }
        }
    }
}
