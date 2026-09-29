<?php

namespace Base\Forge\Entity;

use Base\Entity\User;
use Base\Forge\Entity\Product\HourPack;
use Base\Forge\Enum\QuoteStatus;
use Base\Forge\Repository\QuoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Base\Market\Service\CompanyRegistry;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A special offer made to one client: lines of hours at a rate, a discount,
 * a date it holds until. Asked for from the quote form, priced in the back
 * office, read and accepted by the client - which turns it into a one-off
 * HourPack in their cart (Forge\Service\QuoteToOrder); paying that order
 * credits the hours and marks the quote paid.
 */
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\Table(name: 'forge_quote')]
class Quote implements \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Q-2026-0042: what the client and the studio call it. */
    #[ORM\Column(length: 32, unique: true)]
    private string $reference;

    /** Opaque token of the client's link to the quote. */
    #[ORM\Column(length: 43, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $client = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Email]
    private string $email = '';

    /** The client's company, by its SIREN or SIRET, when they gave one. */
    #[ORM\Column(length: 14, nullable: true)]
    private ?string $siret = null;

    /**
     * What the State's register said of it when the quote was asked
     * (CompanyRegistry): its name, address, whether it trades - or that the
     * register did not answer ("status": unavailable).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $company = null;

    #[ORM\Column(length: 128)]
    #[Assert\NotBlank]
    private string $contactName = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    /** What the client asked for, in their words. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $request = null;

    /** What the studio answers above the lines. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 16, enumType: QuoteStatus::class)]
    private QuoteStatus $status = QuoteStatus::REQUESTED;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $discountPercent = 0;

    #[ORM\Column(length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    /** @var Collection<int, QuoteLine> */
    #[ORM\OneToMany(targetEntity: QuoteLine::class, mappedBy: 'quote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** The one-off product accepting it put in the client's cart. */
    #[ORM\OneToOne(targetEntity: HourPack::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HourPack $product = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $orderReference = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function __construct(string $reference = '')
    {
        $this->reference = $reference;
        $this->token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->lines = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->reference.' · '.$this->title; }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): self { $this->reference = $reference; return $this; }
    public function getToken(): string { return $this->token; }

    public function getClient(): ?User { return $this->client; }
    public function setClient(?User $client): self { $this->client = $client; return $this; }

    public function getSiret(): ?string { return $this->siret; }
    public function setSiret(?string $siret): self { $this->siret = $siret ? CompanyRegistry::normalize($siret) : null; return $this; }

    /** @return array<string, mixed>|null */
    public function getCompany(): ?array { return $this->company; }

    /** Records the register's answer: FOUND with the company, or why not. */
    public function setCompanyCheck(array $lookup): self
    {
        $this->company = ['status' => $lookup['status']] + ($lookup['company']?->toArray() ?? ['checked_at' => (new \DateTimeImmutable())->format(\DATE_ATOM)]);

        return $this;
    }

    /** For the back office: verified, closed, unknown, unchecked. */
    public function getCompanyBadge(): ?string
    {
        return CompanyRegistry::companyBadge($this->siret, $this->company);
    }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getContactName(): string { return $this->contactName; }
    public function setContactName(string $contactName): self { $this->contactName = $contactName; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getRequest(): ?string { return $this->request; }
    public function setRequest(?string $request): self { $this->request = $request; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }

    public function getStatus(): QuoteStatus { return $this->status; }
    public function setStatus(QuoteStatus $status): self { $this->status = $status; return $this; }

    public function getDiscountPercent(): int { return $this->discountPercent; }
    public function setDiscountPercent(int $discountPercent): self { $this->discountPercent = max(0, min(100, $discountPercent)); return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = strtoupper($currency); return $this; }

    public function getValidUntil(): ?\DateTimeImmutable { return $this->validUntil; }
    public function setValidUntil(?\DateTimeImmutable $validUntil): self { $this->validUntil = $validUntil; return $this; }

    public function isExpired(?\DateTimeImmutable $at = null): bool
    {
        return null !== $this->validUntil && $this->validUntil->setTime(23, 59, 59) < ($at ?? new \DateTimeImmutable());
    }

    /** Whether the client may accept it now. */
    public function isAcceptable(): bool
    {
        return $this->status->isOpenToClient() && !$this->isExpired() && $this->getTotalMinutes() > 0;
    }

    /** @return Collection<int, QuoteLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(QuoteLine $line): self
    {
        if (!$this->lines->contains($line)) {
            $line->setPosition($this->lines->count());
            $this->lines->add($line);
            $line->setQuote($this);
        }

        return $this;
    }

    public function removeLine(QuoteLine $line): self
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function getTotalMinutes(): int
    {
        return array_sum($this->lines->map(fn (QuoteLine $line) => $line->getMinutes())->toArray());
    }

    /** Before the discount, in cents. */
    public function getSubtotal(): int
    {
        return array_sum($this->lines->map(fn (QuoteLine $line) => $line->getAmount())->toArray());
    }

    public function getDiscountAmount(): int
    {
        return (int) round($this->getSubtotal() * $this->discountPercent / 100);
    }

    /** What the client pays, in cents. */
    public function getTotal(): int
    {
        return $this->getSubtotal() - $this->getDiscountAmount();
    }

    public function getProduct(): ?HourPack { return $this->product; }
    public function setProduct(?HourPack $product): self { $this->product = $product; return $this; }

    public function getOrderReference(): ?string { return $this->orderReference; }
    public function setOrderReference(?string $orderReference): self { $this->orderReference = $orderReference; return $this; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }

    public function accept(): self
    {
        $this->status = QuoteStatus::ACCEPTED;
        $this->acceptedAt = new \DateTimeImmutable();

        return $this;
    }
}
