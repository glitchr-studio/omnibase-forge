<?php

namespace Base\Forge\Entity;

use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Thread;
use Base\Entity\User;
use Base\Enum\ThreadState;
use Base\Forge\Entity\Product\LicenseOffer;
use Base\Forge\Enum\Pricing;
use Base\Forge\Enum\SoftwareStatus;
use Base\Forge\Repository\SoftwareRepository;
use Base\Service\Model\LinkableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Something the studio made and shows: a client site, a bundle, a JavaScript
 * library, a tool. A base-bundle thread, as market's stores and products are:
 * its title, headline and content are translated (FR/EN), its slug and state
 * come with it - published, it is on the applications page. With a git
 * repository it gets releases; FREE ones download for anyone, LICENSED ones
 * against a licence bought through one of its LicenseOffer products.
 */
#[ORM\Entity(repositoryClass: SoftwareRepository::class)]
#[DiscriminatorEntry(value: 'forge_software')]
class Software extends Thread implements LinkableInterface
{
    public const CATEGORIES = ['site', 'bundle', 'javascript', 'tool', 'game'];

    public static function __iconizeStatic(): ?array
    {
        return ['fa-solid fa-cubes'];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->getRouter()->generate('forge_software', array_merge($routeParameters, ['slug' => $this->getSlug()]), $referenceType);
    }

    #[ORM\Column(length: 16, nullable: true)]
    #[Assert\Choice(choices: self::CATEGORIES)]
    protected ?string $category = 'site';

    #[ORM\Column(length: 16, nullable: true, enumType: SoftwareStatus::class)]
    protected ?SoftwareStatus $status = SoftwareStatus::LIVE;

    #[ORM\Column(length: 16, nullable: true, enumType: Pricing::class)]
    protected ?Pricing $pricing = Pricing::FREE;

    /** @var list<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $stack = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $homepage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $sourceUrl = null;

    /**
     * Where the repository is cloned from (git:sync, the warmer) when it is
     * not declared in git.repositories: forge.repositories_dir/<repository>.git.
     */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $repositoryUrl = null;

    /** The name git/git-bundle knows the repository by (git.repositories.<name>). */
    #[ORM\Column(length: 96, nullable: true)]
    protected ?string $gitRepository = null;

    /** vendor/name in the Composer repository, for a PHP package. */
    #[ORM\Column(length: 128, nullable: true)]
    #[Assert\Regex('/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/')]
    protected ?string $packageName = null;

    /** A path under the public assets, or an absolute URL, for the card. */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $image = null;

    /** The live application, shown in a nested panel from the card. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url]
    protected ?string $demoUrl = null;

    #[ORM\Column(nullable: true)]
    protected ?int $position = 0;

    #[ORM\Column(length: 4, nullable: true)]
    protected ?string $year = null;

    /** @var Collection<int, Release> */
    #[ORM\OneToMany(targetEntity: Release::class, mappedBy: 'software', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['publishedAt' => 'DESC'])]
    protected Collection $releases;

    /** @var Collection<int, LicenseOffer> */
    #[ORM\OneToMany(targetEntity: LicenseOffer::class, mappedBy: 'software')]
    protected Collection $offers;

    public function __construct(?User $owner = null, ?string $title = null, ?string $slug = null)
    {
        parent::__construct($owner, null, $title, $slug);
        $this->releases = new ArrayCollection();
        $this->offers = new ArrayCollection();
    }

    /** The title, as the templates and the admin call it. */
    public function getName(): string { return (string) $this->getTitle(); }
    public function setName(string $name): self { $this->setTitle($name); return $this; }

    /** The one-liner under the name: the thread's (translated) headline. */
    public function getTagline(): ?string { return $this->getHeadline(); }
    public function setTagline(?string $tagline): self { $this->setHeadline($tagline); return $this; }

    /** The long text: the thread's (translated) content. */
    public function getDescription(): ?string { return $this->getContent(); }
    public function setDescription(?string $description): self { $this->setContent($description); return $this; }

    /**
     * Publish (or withdraw) it: on the applications page and downloadable
     * once published - Thread::isVisible() then says so, drafts staying
     * visible to their owners and the admins.
     */
    public function publish(bool $visible = true): self
    {
        $this->setState($visible ? ThreadState::PUBLISH : ThreadState::DRAFT);
        if ($visible && !$this->getPublishedAt()) {
            $this->setPublishedAt(new \DateTime());
        }

        return $this;
    }

    public function getCategory(): string { return $this->category ?? 'site'; }
    public function setCategory(string $category): self
    {
        if (!\in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown software category "%s".', $category));
        }
        $this->category = $category;

        return $this;
    }

    public function getStatus(): SoftwareStatus { return $this->status ?? SoftwareStatus::LIVE; }
    public function setStatus(SoftwareStatus $status): self { $this->status = $status; return $this; }

    public function getPricing(): Pricing { return $this->pricing ?? Pricing::FREE; }
    public function setPricing(Pricing $pricing): self { $this->pricing = $pricing; return $this; }
    public function isFree(): bool { return $this->getPricing()->isFree(); }

    /** @return list<string> */
    public function getStack(): array { return $this->stack ?? []; }
    /** @param list<string> $stack */
    public function setStack(array $stack): self { $this->stack = array_values(array_filter(array_map('trim', $stack))); return $this; }

    /** The stack as the back office edits it: "Symfony, TransparentJS". */
    public function getStackAsText(): string { return implode(', ', $this->getStack()); }
    public function setStackAsText(?string $stack): self { return $this->setStack(explode(',', (string) $stack)); }

    public function getHomepage(): ?string { return $this->homepage; }
    public function setHomepage(?string $homepage): self { $this->homepage = $homepage; return $this; }

    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): self { $this->sourceUrl = $sourceUrl; return $this; }

    public function getRepositoryUrl(): ?string { return $this->repositoryUrl; }
    public function setRepositoryUrl(?string $repositoryUrl): self { $this->repositoryUrl = $repositoryUrl ?: null; return $this; }

    public function getGitRepository(): ?string { return $this->gitRepository; }
    public function setGitRepository(?string $gitRepository): self { $this->gitRepository = $gitRepository ?: null; return $this; }

    public function getPackageName(): ?string { return $this->packageName; }
    public function setPackageName(?string $packageName): self { $this->packageName = $packageName; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }

    public function getDemoUrl(): ?string { return $this->demoUrl; }
    public function setDemoUrl(?string $demoUrl): self { $this->demoUrl = $demoUrl; return $this; }

    public function getPosition(): int { return (int) $this->position; }
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
}
