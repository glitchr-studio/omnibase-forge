<?php

namespace Base\Forge\Entity;

use Base\Forge\Entity\Product\LicenseOffer;
use Base\Forge\Enum\Pricing;
use Base\Forge\Enum\SoftwareStatus;
use Base\Forge\Repository\SoftwareRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Something the studio made and shows: a client site, a bundle, a JavaScript
 * library, a tool. It is on the applications page; with a git repository it
 * gets releases; FREE ones download for anyone, LICENSED ones against a
 * licence bought through one of its LicenseOffer products.
 */
#[ORM\Entity(repositoryClass: SoftwareRepository::class)]
#[ORM\Table(name: 'forge_software')]
class Software implements \Stringable
{
    public const CATEGORIES = ['site', 'bundle', 'javascript', 'tool'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 96, unique: true)]
    #[Assert\NotBlank, Assert\Regex('/^[a-z0-9][a-z0-9\-]*$/')]
    private string $slug = '';

    #[ORM\Column(length: 128)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tagline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16)]
    #[Assert\Choice(choices: self::CATEGORIES)]
    private string $category = 'site';

    #[ORM\Column(length: 16, enumType: SoftwareStatus::class)]
    private SoftwareStatus $status = SoftwareStatus::LIVE;

    #[ORM\Column(length: 16, enumType: Pricing::class)]
    private Pricing $pricing = Pricing::FREE;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $stack = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $homepage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $sourceUrl = null;

    /**
     * Where the repository is cloned from (git:sync, the warmer) when it is
     * not declared in git.repositories: forge.repositories_dir/<repository>.git.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repositoryUrl = null;

    /** The name git/git-bundle knows the repository by (git.repositories.<name>). */
    #[ORM\Column(length: 96, nullable: true)]
    private ?string $repository = null;

    /** vendor/name in the Composer repository, for a PHP package. */
    #[ORM\Column(length: 128, nullable: true)]
    #[Assert\Regex('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/')]
    private ?string $packageName = null;

    /** A path under the public assets, or an absolute URL, for the card. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** The live application, shown in a nested panel from the card. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    private ?string $demoUrl = null;

    #[ORM\Column]
    private bool $visible = true;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $year = null;

    /** @var Collection<int, Release> */
    #[ORM\OneToMany(targetEntity: Release::class, mappedBy: 'software', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['publishedAt' => 'DESC'])]
    private Collection $releases;

    /** @var Collection<int, LicenseOffer> */
    #[ORM\OneToMany(targetEntity: LicenseOffer::class, mappedBy: 'software')]
    private Collection $offers;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name = '', string $slug = '')
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->releases = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->name; }

    public function getId(): ?int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getTagline(): ?string { return $this->tagline; }
    public function setTagline(?string $tagline): self { $this->tagline = $tagline; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): self
    {
        if (!\in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown software category "%s".', $category));
        }
        $this->category = $category;

        return $this;
    }

    public function getStatus(): SoftwareStatus { return $this->status; }
    public function setStatus(SoftwareStatus $status): self { $this->status = $status; return $this; }

    public function getPricing(): Pricing { return $this->pricing; }
    public function setPricing(Pricing $pricing): self { $this->pricing = $pricing; return $this; }
    public function isFree(): bool { return $this->pricing->isFree(); }

    /** @return list<string> */
    public function getStack(): array { return $this->stack; }
    /** @param list<string> $stack */
    public function setStack(array $stack): self { $this->stack = array_values(array_filter(array_map('trim', $stack))); return $this; }

    /** The stack as the back office edits it: "Symfony, TransparentJS". */
    public function getStackAsText(): string { return implode(', ', $this->stack); }
    public function setStackAsText(?string $stack): self { return $this->setStack(explode(',', (string) $stack)); }

    public function getHomepage(): ?string { return $this->homepage; }
    public function setHomepage(?string $homepage): self { $this->homepage = $homepage; return $this; }

    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): self { $this->sourceUrl = $sourceUrl; return $this; }

    public function getRepositoryUrl(): ?string { return $this->repositoryUrl; }
    public function setRepositoryUrl(?string $repositoryUrl): self { $this->repositoryUrl = $repositoryUrl ?: null; return $this; }

    public function getRepository(): ?string { return $this->repository; }
    public function setRepository(?string $repository): self { $this->repository = $repository; return $this; }

    public function getPackageName(): ?string { return $this->packageName; }
    public function setPackageName(?string $packageName): self { $this->packageName = $packageName; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }

    public function getDemoUrl(): ?string { return $this->demoUrl; }
    public function setDemoUrl(?string $demoUrl): self { $this->demoUrl = $demoUrl; return $this; }

    public function isVisible(): bool { return $this->visible; }
    public function setVisible(bool $visible): self { $this->visible = $visible; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function getYear(): ?string { return $this->year; }
    public function setYear(?string $year): self { $this->year = $year; return $this; }

    /** @return Collection<int, Release> newest first */
    public function getReleases(): Collection { return $this->releases; }

    public function addRelease(Release $release): self
    {
        if (!$this->releases->contains($release)) {
            $this->releases->add($release);
            $release->setSoftware($this);
        }

        return $this;
    }

    public function getLatestRelease(): ?Release
    {
        foreach ($this->releases as $release) {
            if ($release->isPublished()) {
                return $release;
            }
        }

        return null;
    }

    public function findRelease(string $version): ?Release
    {
        foreach ($this->releases as $release) {
            if ($release->getVersion() === $version) {
                return $release;
            }
        }

        return null;
    }

    /** @return Collection<int, LicenseOffer> */
    public function getOffers(): Collection { return $this->offers; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
