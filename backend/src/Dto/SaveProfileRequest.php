<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One autosave tick: the version the client last saw, plus every attribute
 * value it currently holds.
 *
 * The whole section is sent rather than a diff — the payload is small, and it
 * keeps the client from having to track which individual fields are dirty.
 */
final class SaveProfileRequest
{
    /**
     * @param array<int|string, mixed> $values attributeId => value
     */
    public function __construct(
        /**
         * Optimistic locking: the server refuses the write if the profile has
         * moved on since the client read it.
         */
        #[Assert\NotNull(message: 'Version is required.')]
        #[Assert\PositiveOrZero]
        public readonly int $version = 0,

        #[Assert\Type('array')]
        #[Assert\Count(max: 200, maxMessage: 'Too many attributes in one save.')]
        public readonly array $values = [],
    ) {
    }

    /**
     * @return array<int, mixed> keyed by attribute id
     */
    public function normalizedValues(): array
    {
        $normalized = [];

        foreach ($this->values as $attributeId => $value) {
            if (is_numeric($attributeId)) {
                $normalized[(int) $attributeId] = $value;
            }
        }

        return $normalized;
    }
}
