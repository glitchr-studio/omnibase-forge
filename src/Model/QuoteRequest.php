<?php

namespace Base\Forge\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the quote form collects. A DTO, not the Quote itself: base-bundle's
 * form factory refuses an entity as form data (a half-filled entity in the
 * unit of work is a flush waiting to happen); ShopController::request()
 * turns it into a Quote once it is valid.
 */
final class QuoteRequest
{
    #[Assert\NotBlank, Assert\Length(max: 128)]
    public string $contactName = '';

    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank, Assert\Length(max: 180)]
    public string $title = '';

    #[Assert\NotBlank, Assert\Length(min: 30, max: 6000)]
    public string $request = '';
}
