<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class EditCvAttributeRequest
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $attributeId = 0,

        #[Assert\NotNull(message: 'Version is required.')]
        #[Assert\PositiveOrZero]
        public readonly int $version = 0,
        public readonly mixed $value = null,
    ) {
    }
}
