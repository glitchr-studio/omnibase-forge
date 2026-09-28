<?php

namespace Base\Forge\Model;

use Symfony\Component\Validator\Constraints as Assert;

/** What a piece of software sends to free its own machine's place. */
final class ReleaseRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $key = '',
        #[Assert\NotBlank, Assert\Length(min: 8, max: 255)]
        public readonly string $machine = '',
    ) {
    }
}
