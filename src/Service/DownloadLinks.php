<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Artifact;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The links an artifact is downloaded through: signed, and valid for
 * forge.download_ttl seconds. Whoever holds one within that time gets the
 * file - the licence was checked when it was handed out.
 */
class DownloadLinks
{
    public function __construct(
        private readonly UriSigner $signer,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%forge.download_ttl%')] private readonly int $ttl = 600,
    ) {
    }

    /** @param array<string, scalar> $context who it was issued to (license id, channel) */
    public function sign(Artifact $artifact, array $context = [], ?int $ttl = null): string
    {
        $url = $this->router->generate('forge_download_file', ['id' => $artifact->getId(), 'filename' => $artifact->getFilename()] + $context, UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->signer->sign($url, new \DateTimeImmutable(sprintf('+%d seconds', $ttl ?? $this->ttl)));
    }

    public function verify(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }
}
