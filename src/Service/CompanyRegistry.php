<?php

namespace Base\Forge\Service;

use Base\Forge\Model\CompanyRecord;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Who a French company is, from its SIREN (9 digits) or SIRET (14): the
 * State's free register, API Recherche d'entreprises
 * (recherche-entreprises.api.gouv.fr: no key; INSEE's Sirene and the RNE -
 * the same companies as Infogreffe's, without its fee). The number is
 * checked here first (its length, its Luhn key), then looked up, the answer
 * kept a day.
 */
class CompanyRegistry
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const INVALID = 'invalid';
    public const UNAVAILABLE = 'unavailable';

    private const ENDPOINT = 'https://recherche-entreprises.api.gouv.fr/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** The digits of a SIREN or SIRET as typed ("552 100 554 00054"). */
    public static function normalize(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number);
    }

    /** A SIREN or a SIRET by its form: 9 or 14 digits, and its Luhn key (La Poste's SIRETs aside). */
    public static function isWellFormed(?string $number): bool
    {
        $digits = self::normalize($number);
        if (!\in_array(\strlen($digits), [9, 14], true)) {
            return false;
        }
        if (14 === \strlen($digits) && str_starts_with($digits, '356000000')) {
            return 0 === array_sum(str_split($digits)) % 5;
        }

        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $value = (int) $digit * (1 === $i % 2 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return 0 === $sum % 10;
    }

    /**
     * @return array{status: string, company: ?CompanyRecord} FOUND with the
     *         company, or NOT_FOUND, INVALID (not a SIREN/SIRET), UNAVAILABLE
     *         (the register did not answer: not the visitor's fault)
     */
    public function lookup(?string $number): array
    {
        $digits = self::normalize($number);
        if (!self::isWellFormed($digits)) {
            return ['status' => self::INVALID, 'company' => null];
        }

        try {
            $data = $this->cache->get('forge.company.'.$digits, function (ItemInterface $item) use ($digits) {
                $item->expiresAfter(86400);
                $response = $this->httpClient->request('GET', self::ENDPOINT, [
                    'query' => ['q' => $digits, 'per_page' => 1, 'page' => 1],
                    'timeout' => 6,
                    'headers' => ['Accept' => 'application/json'],
                ]);

                return $this->record($response->toArray(), $digits)?->toArray() ?? [];
            });
        } catch (\Throwable $e) {
            $this->logger?->warning('The company register did not answer for {number}: {error}', ['number' => $digits, 'error' => $e->getMessage()]);

            return ['status' => self::UNAVAILABLE, 'company' => null];
        }

        return $data
            ? ['status' => self::FOUND, 'company' => CompanyRecord::fromArray($data)]
            : ['status' => self::NOT_FOUND, 'company' => null];
    }

    /**
     * One line for the back office, from a stored check: "✓ Name - address"
     * (verified, trading), "⚠ closed", "? not found", "… unchecked".
     *
     * @param array<string, mixed>|null $check
     */
    public static function companyBadge(?string $siret, ?array $check): ?string
    {
        if (!$siret) {
            return null;
        }

        return match ($check['status'] ?? null) {
            self::FOUND => (($check['active'] ?? false) ? '✓ ' : '⚠ FERMÉE · ').($check['name'] ?? '').(isset($check['address']) ? ' - '.$check['address'] : ''),
            self::NOT_FOUND => '? introuvable au registre ('.$siret.')',
            self::INVALID => '✗ numéro invalide ('.$siret.')',
            default => '… non vérifiée ('.$siret.')',
        };
    }

    /** @param array<string, mixed> $payload */
    private function record(array $payload, string $digits): ?CompanyRecord
    {
        $result = $payload['results'][0] ?? null;
        if (!\is_array($result) || !str_starts_with($digits, (string) ($result['siren'] ?? '-'))) {
            return null;
        }

        // A SIRET: that establishment - it may be closed while the company trades on.
        $establishment = $result['siege'] ?? [];
        if (14 === \strlen($digits)) {
            foreach ([$result['siege'] ?? [], ...($result['matching_etablissements'] ?? [])] as $candidate) {
                if (($candidate['siret'] ?? null) === $digits) {
                    $establishment = $candidate;
                    break;
                }
            }
            if (($establishment['siret'] ?? null) !== $digits) {
                return null;
            }
        }

        $active = 'A' === ($result['etat_administratif'] ?? null)
            && (14 !== \strlen($digits) || 'A' === ($establishment['etat_administratif'] ?? null));

        return new CompanyRecord(
            siren: (string) $result['siren'],
            siret: $establishment['siret'] ?? null,
            name: (string) ($result['nom_raison_sociale'] ?? $result['nom_complet'] ?? ''),
            address: $establishment['adresse'] ?? null,
            activity: $result['activite_principale'] ?? null,
            legalForm: $result['nature_juridique'] ?? null,
            active: $active,
            createdOn: $result['date_creation'] ?? null,
        );
    }
}
