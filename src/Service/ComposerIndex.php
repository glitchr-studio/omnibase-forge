<?php

namespace Base\Forge\Service;

use Base\Entity\User;
use Base\Forge\Entity\License;
use Base\Forge\Entity\Release;
use Base\Forge\Entity\Software;
use Base\Forge\Repository\LicenseRepository;
use Base\Forge\Repository\SoftwareRepository;
use Git\Service\Git2Service;
use Psr\Log\LoggerInterface;

/**
 * The studio's own Packagist: the index (packages.json) of a Composer repository of the
 * PHP packages among the software. Free ones are listed for anyone; licensed
 * ones for the holder of a valid licence, each version its licence covers,
 * with a dist URL signed for that licence.
 *
 *     composer config repositories.glitchr composer https://glitchr.io/composer
 *     composer config http-basic.glitchr.io <email> <licence key>
 *     composer require glitchr/<package>
 *
 * Each version's metadata is its own composer.json at that tag, read through
 * git/git-bundle, with name, version, dist and source set here.
 */
class ComposerIndex
{
    public function __construct(
        private readonly SoftwareRepository $software,
        private readonly LicenseRepository $licenses,
        private readonly DownloadLinks $links,
        private readonly Git2Service $git,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param list<License> $licenses the caller's valid licences (empty: anonymous)
     *
     * @return array{packages: array<string, array<string, array>>}
     */
    public function packages(array $licenses = []): array
    {
        $bySoftware = [];
        foreach ($licenses as $license) {
            if ($license->isValid()) {
                $bySoftware[(int) $license->getSoftware()?->getId()][] = $license;
            }
        }

        $packages = [];
        foreach ($this->software->findPackages() as $software) {
            $held = $bySoftware[$software->getId()] ?? [];
            if (!$software->isFree() && !$held) {
                continue;
            }

            foreach ($software->getReleases() as $release) {
                $license = $software->isFree() ? null : $this->covering($held, $release);
                if (!$release->isPublished() || !$release->getArtifact('zip') || (!$software->isFree() && !$license)) {
                    continue;
                }
                if ($package = $this->package($software, $release, $license)) {
                    $packages[$software->getPackageName()][$release->getVersion()] = $package;
                }
            }
        }

        return ['packages' => $packages];
    }

    /** The user behind a Basic auth pair (e-mail, licence key), and their valid licences. */
    public function authenticate(?string $email, ?string $key): array
    {
        if (!$email || !$key || !LicenseKey::looksValid($key)) {
            return [null, []];
        }

        $license = $this->licenses->findOneByKey($key);
        if (!$license || !$license->isValid() || 0 !== strcasecmp((string) $license->getOwner()?->getEmail(), $email)) {
            return [null, []];
        }

        /** @var User $owner */
        $owner = $license->getOwner();
        $valid = array_filter($this->licenses->findOwnedBy($owner), fn (License $l) => $l->isValid());

        return [$owner, array_values($valid)];
    }

    /** @param list<License> $licenses */
    private function covering(array $licenses, Release $release): ?License
    {
        foreach ($licenses as $license) {
            if ($license->covers($release)) {
                return $license;
            }
        }

        return null;
    }

    private function package(Software $software, Release $release, ?License $license): ?array
    {
        $manifest = [];
        try {
            $blob = $this->git->getBlob((string) $software->getGitRepository(), (string) ($release->getTag() ?? $release->getCommitSha()), 'composer.json');
            $manifest = json_decode($blob['content'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger?->warning('No composer.json for {software} {version}: {error}', ['software' => $software->getName(), 'version' => $release->getVersion(), 'error' => $e->getMessage()]);
        }

        $artifact = $release->getArtifact('zip');
        // Composer caches dist files by URL and reference: a long-lived
        // signature keeps a cached install valid while the licence is.
        $url = $this->links->sign($artifact, array_filter(['license' => $license?->getId(), 'channel' => 'composer']), 60 * 60 * 24 * 30);

        return array_merge(
            \is_array($manifest) ? $manifest : [],
            [
                'name' => $software->getPackageName(),
                'version' => $release->getVersion(),
                'dist' => ['type' => 'zip', 'url' => $url, 'reference' => $release->getCommitSha()],
                'time' => $release->getPublishedAt()?->format(DATE_ATOM),
            ],
            $software->isFree() && $software->getSourceUrl() ? ['source' => ['type' => 'git', 'url' => $software->getSourceUrl(), 'reference' => $release->getCommitSha()]] : [],
        );
    }
}
