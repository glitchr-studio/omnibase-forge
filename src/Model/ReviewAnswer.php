<?php

namespace Base\Forge\Model;

use Symfony\Component\Validator\Constraints as Assert;

/** What the client answers about their project. */
final class ReviewAnswer
{
    #[Assert\NotNull(message: 'forge.review.rating_required')]
    #[Assert\Range(min: 1, max: 5)]
    public ?int $rating = null;

    #[Assert\Length(max: 2000)]
    public ?string $comment = null;

    #[Assert\Length(max: 128)]
    public ?string $signature = null;
}
