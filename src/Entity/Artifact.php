<?php

namespace Base\Forge\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A file a release is downloaded as, stored under forge.storage_dir (never
 * in the web root) and handed out by DownloadController.
 */
#[ORM\Entity]
#[ORM\Table(name: 'forge_artifact')]
class Artifact implements \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Release::class, inversedBy: 'artifacts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Release $release = null;

    /** Relative to forge.storage_dir: <software>/<version>/<filename>. */
    #[ORM\Column(length: 255)]
    private string $path = '';

    #[ORM\Column(length: 16)]
    private string $format = 'zip';

    #[ORM\Column(type: 'bigint')]
    private int|string $size = 0;

    #[ORM\Column(length: 64)]
    private string $sha256 = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(?Release $release = null, string $path = '', string $format = 'zip', int $size = 0, string $sha256 = '')
    {
        $this->path = $path;
        $this->format = $format;
        $this->size = $size;
        $this->sha256 = $sha256;
        $this->createdAt = new \DateTimeImmutable();
        $release?->addArtifact($this);
    }

    public function __toString(): string { return $this->getFilename(); }

    public function getId(): ?int { return $this->id; }

    public function getRelease(): ?Release { return $this->release; }
    public function setRelease(?Release $release): self { $this->release = $release; return $this; }

    public function getSoftware(): ?Software { return $this->release?->getSoftware(); }

    public function getPath(): string { return $this->path; }
    public function getFilename(): string { return basename($this->path); }

    public function getFormat(): string { return $this->format; }
    public function getSize(): int { return (int) $this->size; }
    public function getSha256(): string { return $this->sha256; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isFree(): bool { return (bool) $this->getSoftware()?->isFree(); }
}
