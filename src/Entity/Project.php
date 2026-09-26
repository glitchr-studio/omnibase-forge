<?php

namespace Base\Forge\Entity;

use Base\Entity\User;
use Base\Forge\Enum\CardColumn;
use Base\Forge\Enum\ProjectStatus;
use Base\Forge\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A client's project: what the dashboard shows them - the board, the time
 * the studio logged on it, and the history of its git repository.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'forge_project')]
class Project implements \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $client = null;

    #[ORM\Column(length: 128)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $summary = null;

    /**
     * Where the repository is cloned from (git:sync, the warmer) when it is
     * not declared in git.repositories: forge.repositories_dir/<repository>.git.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repositoryUrl = null;

    /** The name git/git-bundle knows the repository by. */
    #[ORM\Column(length: 96, nullable: true)]
    private ?string $repository = null;

    #[ORM\Column(length: 16, enumType: ProjectStatus::class)]
    private ProjectStatus $status = ProjectStatus::OPEN;

    /** Minutes agreed for the project; null when it runs on support hours only. */
    #[ORM\Column(nullable: true)]
    private ?int $budgetMinutes = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueOn = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Card> */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $cards;

    /** @var Collection<int, TimeEntry> */
    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['spentOn' => 'DESC'])]
    private Collection $timeEntries;

    public function __construct(?User $client = null, string $name = '')
    {
        $this->client = $client;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->cards = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
    }

    public function __toString(): string { return $this->name; }

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?User { return $this->client; }
    public function setClient(User $client): self { $this->client = $client; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): self { $this->summary = $summary; return $this; }

    public function getRepositoryUrl(): ?string { return $this->repositoryUrl; }
    public function setRepositoryUrl(?string $repositoryUrl): self { $this->repositoryUrl = $repositoryUrl ?: null; return $this; }

    public function getRepository(): ?string { return $this->repository; }
    public function setRepository(?string $repository): self { $this->repository = $repository ?: null; return $this; }

    public function getStatus(): ProjectStatus { return $this->status; }
    public function setStatus(ProjectStatus $status): self { $this->status = $status; return $this; }

    public function getBudgetMinutes(): ?int { return $this->budgetMinutes; }
    public function setBudgetMinutes(?int $budgetMinutes): self { $this->budgetMinutes = $budgetMinutes; return $this; }

    public function getDueOn(): ?\DateTimeImmutable { return $this->dueOn; }
    public function setDueOn(?\DateTimeImmutable $dueOn): self { $this->dueOn = $dueOn; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Card> */
    public function getCards(): Collection { return $this->cards; }

    /** @return array<string, list<Card>> the board, column by column, in position order */
    public function getBoard(): array
    {
        $board = [];
        foreach (CardColumn::cases() as $column) {
            $board[$column->value] = [];
        }
        foreach ($this->cards as $card) {
            $board[$card->getColumn()->value][] = $card;
        }

        return $board;
    }

    public function addCard(Card $card): self
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setProject($this);
        }

        return $this;
    }

    /** @return Collection<int, TimeEntry> newest first */
    public function getTimeEntries(): Collection { return $this->timeEntries; }

    public function addTimeEntry(TimeEntry $entry): self
    {
        if (!$this->timeEntries->contains($entry)) {
            $this->timeEntries->add($entry);
            $entry->setProject($this);
        }

        return $this;
    }

    public function getSpentMinutes(): int
    {
        return array_sum($this->timeEntries->map(fn (TimeEntry $entry) => $entry->getMinutes())->toArray());
    }

    /** 0-100: the share of cards done, or of the budget spent when there are no cards. */
    public function getProgress(): int
    {
        $cards = $this->cards->count();
        if ($cards > 0) {
            $done = $this->cards->filter(fn (Card $card) => CardColumn::DONE === $card->getColumn())->count();

            return (int) round(100 * $done / $cards);
        }
        if ($this->budgetMinutes) {
            return (int) min(100, round(100 * $this->getSpentMinutes() / $this->budgetMinutes));
        }

        return ProjectStatus::DONE === $this->status ? 100 : 0;
    }
}
