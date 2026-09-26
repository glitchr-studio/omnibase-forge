<?php

namespace Tests\Base\Forge\Service;

use Base\Forge\Entity\HourCredit;
use Base\Forge\Entity\Project;
use Base\Forge\Entity\TimeEntry;
use Base\Forge\Enum\CreditReason;
use Base\Forge\Repository\HourCreditRepository;
use Base\Forge\Service\HourLedger;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\Forge\ForgeKernelTestCase;

class HourLedgerTest extends ForgeKernelTestCase
{
    private function ledger(): HourLedger
    {
        return new HourLedger($this->createStub(EntityManagerInterface::class), $this->createStub(HourCreditRepository::class));
    }

    public function testLoggedTimeIsDebitedFromTheClient(): void
    {
        $client = $this->user();
        $entry = new TimeEntry(90, 'Fix checkout');
        (new Project($client, 'Shop'))->addTimeEntry($entry);

        $credit = $this->ledger()->debitFor($entry, null);

        self::assertSame(-90, $credit->getMinutes());
        self::assertSame(CreditReason::TIME, $credit->getReason());
        self::assertSame($client, $credit->getUser());
        self::assertSame($entry, $credit->getTimeEntry());
    }

    public function testAnEditedEntryUpdatesItsLineInPlace(): void
    {
        $entry = new TimeEntry(30, 'Call');
        (new Project($this->user(), 'Shop'))->addTimeEntry($entry);
        $existing = $this->ledger()->debitFor($entry, null);

        $entry->setMinutes(45);
        self::assertSame($existing, $this->ledger()->debitFor($entry, $existing));
        self::assertSame(-45, $existing->getMinutes());
    }

    public function testNonBillableTimeCostsNothing(): void
    {
        $entry = (new TimeEntry(120, 'Internal refactoring'))->setBillable(false);
        (new Project($this->user(), 'Shop'))->addTimeEntry($entry);

        self::assertNull($this->ledger()->debitFor($entry, null));
        self::assertNull($this->ledger()->debitFor(new TimeEntry(60, 'No project yet'), null));
    }

    public function testMinutesFormatAsHours(): void
    {
        self::assertSame('3h30', TimeEntry::formatMinutes(210));
        self::assertSame('0h05', TimeEntry::formatMinutes(5));
        self::assertSame('-1h15', TimeEntry::formatMinutes(-75));
    }
}
