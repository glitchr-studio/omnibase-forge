<?php

namespace Base\Forge\Service;

use Base\Forge\Entity\Artifact;
use Base\Forge\Entity\Release;
use Git\Service\Git2Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds a release's zip straight from its git tag - every file of the tree,
 * read by git/git-bundle from the object database, under a
 * <software>-<version>/ folder. Files marked export-ignore are not filtered
 * (there is no .gitattributes evaluation here): keep the tree clean.
 */
class ArtifactBuilder
{
    public function __construct(
        private readonly Git2Service $git,
        #[Autowire('%forge.storage_dir%')] private readonly string $storageDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function build(Release $release): Artifact
    {
        $software = $release->getSoftware() ?? throw new \LogicException('A release without software has nothing to build.');
        $repository = $software->getGitRepository() ?? throw new \LogicException(sprintf('%s has no git repository to build from.', $software));
        $ref = $release->getTag() ?? $release->getCommitSha() ?? throw new \LogicException(sprintf('%s has neither tag nor commit.', $release));

        $folder = sprintf('%s-%s', $software->getSlug(), $release->getVersion());
        $relative = sprintf('%s/%s/%s.zip', $software->getSlug(), $release->getVersion(), $folder);
        $target = rtrim($this->storageDir, '/').'/'.$relative;
        $this->filesystem->mkdir(\dirname($target));

        $temporary = $target.'.part';
        $zip = new \ZipArchive();
        if (true !== $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException(sprintf('Cannot write "%s".', $temporary));
        }

        $count = 0;
        foreach ($this->git->walkTree($repository, $ref) as $path => $file) {
            $name = $folder.'/'.$path;
            $zip->addFromString($name, $file['content']);
            // Keep the executable bit (100755) of scripts.
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, ($file['filemode'] & 0777 ?: 0644) << 16);
            ++$count;
        }
        if (0 === $count) {
            $zip->addEmptyDir($folder);
        }
        $zip->close();

        $this->filesystem->rename($temporary, $target, true);

        foreach ($release->getArtifacts() as $previous) {
            if ('zip' === $previous->getFormat()) {
                $release->getArtifacts()->removeElement($previous);
            }
        }

        return new Artifact($release, $relative, 'zip', (int) filesize($target), (string) hash_file('sha256', $target));
    }

    public function absolutePath(Artifact $artifact): string
    {
        return rtrim($this->storageDir, '/').'/'.$artifact->getPath();
    }
}
