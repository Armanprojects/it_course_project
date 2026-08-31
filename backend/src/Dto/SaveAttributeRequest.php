<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Create or edit one library attribute.
 *
 * The type is only read on create: changing it later would invalidate every
 * value already stored against the attribute, so the entity has no setter for
 * it and the update path ignores the field.
 */
final class SaveAttributeRequest
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название атрибута.')]
        #[Assert\Length(max: 120, maxMessage: 'Название не длиннее {{ limit }} символов.')]
        public readonly string $name = '',

        #[Assert\Length(max: 20000)]
        public readonly ?string $description = null,

        #[Assert\NotBlank(message: 'Выберите категорию.')]
        #[Assert\Choice(
            callback: [self::class, 'categories'],
            message: 'Неизвестная категория.',
        )]
        public readonly string $category = 'domain_knowledge',

        #[Assert\NotBlank(message: 'Выберите тип.')]
        #[Assert\Choice(
            callback: [self::class, 'types'],
            message: 'Неизвестный тип.',
        )]
        public readonly string $type = 'string',

        #[Assert\Count(max: 100, maxMessage: 'Не больше {{ limit }} вариантов.')]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 255)])]
        public readonly array $options = [],

        #[Assert\PositiveOrZero]
        public readonly ?int $version = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_map(
            static fn (AttributeCategory $case): string => $case->value,
            AttributeCategory::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_map(
            static fn (AttributeType $case): string => $case->value,
            AttributeType::cases(),
        );
    }

    public function categoryEnum(): AttributeCategory
    {
        return AttributeCategory::from($this->category);
    }

    public function typeEnum(): AttributeType
    {
        return AttributeType::from($this->type);
    }

    /**
     * Trimmed and de-duplicated; a dropdown with two identical choices is a
     * data-entry slip, not a configuration.
     *
     * @return list<string>
     */
    public function cleanOptions(): array
    {
        $seen = [];

        foreach ($this->options as $option) {
            $value = trim((string) $option);

            if ('' !== $value) {
                $seen[$value] = $value;
            }
        }

        return array_values($seen);
    }
}
