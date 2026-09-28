<?php

namespace Base\Forge\Twig;

use Base\Forge\Entity\TimeEntry;
use Base\Service\TradingInterface;
use Twig\Attribute\AsTwigFilter;

/**
 * {{ 210|forge_hours }} → 3h30; {{ 2500|forge_money('EUR') }} → 25,00 €;
 * {{ 2500|forge_price('EUR') }} → 25,00 € ≈ 27,30 $US for a visitor who reads
 * dollars (base-bundle's Trading rendered currency, with its rate).
 */
final class ForgeExtension
{
    public function __construct(private readonly ?TradingInterface $trading = null)
    {
    }

    #[AsTwigFilter('forge_hours')]
    public function hours(?int $minutes): string
    {
        return TimeEntry::formatMinutes((int) $minutes);
    }

    #[AsTwigFilter('forge_money')]
    public function money(?int $cents, string $currency = 'EUR', ?string $locale = null): string
    {
        $formatter = new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency(((int) $cents) / 100, $currency);
    }

    /**
     * A price as it is charged, then what it comes to in the visitor's
     * currency when that is another and a rate is known: indicative only,
     * the payment stays in the price's own currency.
     */
    #[AsTwigFilter('forge_price', isSafe: ['html'])]
    public function price(?int $cents, string $currency = 'EUR', ?string $locale = null): string
    {
        $price = htmlspecialchars($this->money($cents, $currency, $locale));

        $target = $this->trading?->getRenderedCurrency();
        $rate = $target && $target !== $currency ? $this->trading->getFallback($currency, $target)?->getValue() : null;
        if (!$rate) {
            return $price;
        }

        return $price.' <small class="forge-approx">≈ '.htmlspecialchars($this->money((int) round((int) $cents * $rate), $target, $locale)).'</small>';
    }
}
