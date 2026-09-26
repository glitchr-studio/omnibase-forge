<?php

namespace Base\Forge\Twig;

use Base\Forge\Entity\TimeEntry;
use Twig\Attribute\AsTwigFilter;

/** {{ 210|forge_hours }} → 3h30; {{ 2500|forge_money('EUR') }} → 25,00 €. */
final class ForgeExtension
{
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
}
