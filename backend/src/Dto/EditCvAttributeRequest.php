<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One in-place edit on the CV page: which attribute, the new value, and the
 * profile version the client last saw.
 *
 * The value stays loosely typed — the attribute's own type decides how to read
 * it, which AttributeValueWriter does for the profile endpoint too.
 */
final class EditCvAttributeRequest
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $attributeId = 0,

        /**
         * Optimistic locking against the profile, since that is where the
         * value actually lives.
         */
        #[Assert\NotNull(message: 'Version is required.')]
        #[Assert\PositiveOrZero]
        public readonly int $version = 0,

        public readonly mixed $value = null,
    ) {
    }
}
