<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Release;
use Base\Forge\Entity\Software;
use Doctrine\ORM\EntityManagerInterface;
use Git\Service\Git2Service;

/**
 * Tags become releases: for each software with a repository, every tag that
 * reads as a version (v1.2.3, 1.2, 2.0.0-beta1) and has no release yet gets
 * one - its commit, its message as changelog, its date as publication - and
 * its zip.
 */
class ReleaseSync
{
    public const VERSION = '/^v?(\d+(?:\.\d+){0,3}(?:[-.]?(?:alpha|beta|rc|patch|p)\.?\d*)?)$/i';

    public function __construct(
        private readonly Git2Service $git,
        private readonly ArtifactBuilder $builder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<Release> the releases created, flushed */
    public function sync(Software $software, bool $build = true): array
    {
        $repository = $software->getGitRepository();
        if (!$repository || !$this->git->hasRepository($repository)) {
            return [];
        }

        $created = [];
        foreach ($this->git->getTags($repository) as $name => $tag) {
            if (!preg_match(self::VERSION, $name, $match) || $software->findRelease($match[1])) {
                continue;
            }

            $details = $this->git->getTag($repository, $name);
            $release = new Release($software, $match[1]);
            $release->setTag($name);
            $release->setCommitSha($details['sha']);
            $release->setChangelog($details['message']);
            $release->setPublishedAt($details['date']);

            if ($build) {
                $this->builder->build($release);
            }

            $this->entityManager->persist($release);
            $created[] = $release;
        }

        $this->entityManager->flush();

        return $created;
    }

    /** Rebuild one release's archive (a tag moved, the storage was lost). Flushed. */
    public function rebuild(Release $release): void
    {
        $this->builder->build($release);
        $this->entityManager->flush();
    }
}
