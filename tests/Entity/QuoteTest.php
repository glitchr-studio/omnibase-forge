<?php

namespace Tests\Base\Forge\Entity;

use Base\Forge\Entity\Quote;
use Base\Forge\Entity\QuoteLine;
use Base\Forge\Enum\QuoteStatus;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    public function testTotalsInMinutesAndCents(): void
    {
        $quote = new Quote('Q-2026-0001');
        $quote->addLine(new QuoteLine('Maquettes', 90, 6000));   // 1h30 at 60 € = 90 €
        $quote->addLine((new QuoteLine('Intégration', 0, 7500))->setHours(2.25)); // 2h15 at 75 € = 168.75 €
        $quote->setDiscountPercent(10);

        self::assertSame(225, $quote->getTotalMinutes());
        self::assertSame(25875, $quote->getSubtotal());
        self::assertSame(2588, $quote->getDiscountAmount());
        self::assertSame(23287, $quote->getTotal());
        self::assertSame([0, 1], $quote->getLines()->map(fn (QuoteLine $line) => $line->getPosition())->getValues());
    }

    public function testOnlyASentValidQuoteWithHoursCanBeAccepted(): void
    {
        $quote = (new Quote('Q-2026-0002'))->addLine(new QuoteLine('Support', 60, 6000));
        self::assertFalse($quote->isAcceptable(), 'still a request');

        $quote->setStatus(QuoteStatus::SENT);
        self::assertTrue($quote->isAcceptable());

        $quote->setValidUntil(new \DateTimeImmutable('-2 days'));
        self::assertTrue($quote->isExpired());
        self::assertFalse($quote->isAcceptable());

        $quote->setValidUntil(new \DateTimeImmutable('today'));
        self::assertFalse($quote->isExpired(), 'valid through the whole of its last day');

        $empty = (new Quote('Q-2026-0003'))->setStatus(QuoteStatus::SENT);
        self::assertFalse($empty->isAcceptable(), 'nothing to sell');
    }

    public function testTheClientLinkTokenIsUrlSafe(): void
    {
        $token = (new Quote('Q'))->getToken();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_\-]{43}$/', $token);
        self::assertNotSame($token, (new Quote('Q'))->getToken());
    }
}
