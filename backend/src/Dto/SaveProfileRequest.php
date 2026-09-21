<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SaveProfileRequest
{
    /** @param array<int|string, mixed> $values attributeId => value */
    public function __construct(

        #[Assert\NotNull(message: 'Version is required.')]
        #[Assert\PositiveOrZero]
        public readonly int $version = 0,

        #[Assert\Type('array')]
        #[Assert\Count(max: 200, maxMessage: 'Too many attributes in one save.')]
        public readonly array $values = [],
    ) {
    }

    /** @return array<int, mixed> keyed by attribute id */
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
