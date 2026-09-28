<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\LicenseActivation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The proof an activated machine keeps, to run without asking again: what
 * the licence allows, for that machine, until when - signed with the
 * studio's Ed25519 key, so the software checks it offline with the public
 * key it ships with, and nothing it holds can forge one.
 *
 *     token = base64url(payload JSON) "." base64url(Ed25519 signature of the payload JSON)
 *
 * The payload:
 *   v                1
 *   license          the licence's id
 *   software         the software's slug - a token for another is refused
 *   email            the seat's e-mail
 *   machine          SHA-256 of the machine identifier - the software compares it to its own
 *   issued_at        UNIX time
 *   valid_until      UNIX time: past it, the software asks again (FORGE_LICENSE_GRACE_DAYS,
 *                    never beyond the licence's own expiry)
 *   license_expires  UNIX time, or null: perpetual
 *   updates_until    UNIX time, or null: the releases published until then are covered
 *
 * The secret key is FORGE_LICENSE_SIGNING_KEY (base64 of sodium's 64-byte
 * secret key; bin/console forge:license:keypair makes a pair): a secret,
 * in the vault, never in the repository.
 */
class LicenseToken
{
    public function __construct(
        #[Autowire('%env(default::FORGE_LICENSE_SIGNING_KEY)%')] private readonly ?string $secretKey = null,
        #[Autowire('%env(default::FORGE_LICENSE_GRACE_DAYS)%')] private readonly ?string $graceDays = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->secret();
    }

    /** The public key the software ships with, base64. */
    public function publicKey(): ?string
    {
        $secret = $this->secret();

        return $secret ? base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)) : null;
    }

    /**
     * @return array{token: string, payload: array<string, mixed>}
     */
    public function issue(LicenseActivation $activation, ?\DateTimeImmutable $at = null): array
    {
        $secret = $this->secret() ?? throw new \LogicException('FORGE_LICENSE_SIGNING_KEY is not set: bin/console forge:license:keypair');
        $at ??= new \DateTimeImmutable();
        $seat = $activation->getSeat();
        $license = $seat?->getLicense() ?? throw new \LogicException('An activation without its licence.');

        $validUntil = $at->modify(sprintf('+%d days', max(1, (int) $this->graceDays ?: 30)));
        if ($license->getExpiresAt() && $license->getExpiresAt() < $validUntil) {
            $validUntil = $license->getExpiresAt();
        }

        $payload = [
            'v' => 1,
            'license' => $license->getId(),
            'software' => $license->getSoftware()?->getSlug(),
            'email' => $seat->getEmail(),
            'machine' => $activation->getMachine(),
            'issued_at' => $at->getTimestamp(),
            'valid_until' => $validUntil->getTimestamp(),
            'license_expires' => $license->getExpiresAt()?->getTimestamp(),
            'updates_until' => $license->getUpdatesUntil()?->getTimestamp(),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'token' => self::encode($json).'.'.self::encode(sodium_crypto_sign_detached($json, $secret)),
            'payload' => $payload,
        ];
    }

    /**
     * What the software does, offline: the payload when the signature is the
     * studio's, else null. Here for the tests and for tools written in PHP.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $publicKey): ?array
    {
        $parts = explode('.', $token);
        if (2 !== \count($parts)) {
            return null;
        }
        $json = self::decode($parts[0]);
        $signature = self::decode($parts[1]);
        $key = base64_decode($publicKey, true);
        if (false === $json || false === $signature || false === $key || SODIUM_CRYPTO_SIGN_BYTES !== \strlen($signature) || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== \strlen($key)) {
            return null;
        }
        if (!sodium_crypto_sign_verify_detached($signature, $json, $key)) {
            return null;
        }

        return json_decode($json, true);
    }

    /** @return array{secret: string, public: string} a new pair, base64 */
    public static function keypair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    private function secret(): ?string
    {
        if (!$this->secretKey) {
            return null;
        }
        $secret = base64_decode(trim($this->secretKey), true);

        return false !== $secret && SODIUM_CRYPTO_SIGN_SECRETKEYBYTES === \strlen($secret) ? $secret : null;
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): string|false
    {
        return base64_decode(strtr($text, '-_', '+/'), true);
    }
}
