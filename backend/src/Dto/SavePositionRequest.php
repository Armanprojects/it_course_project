<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Position;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The whole position as the editor holds it: basics, template attributes,
 * access rules and project tags in one payload.
 *
 * Sent as a whole rather than as a diff so that the server never has to guess
 * what "removed" means — anything absent from the lists is gone.
 */
final class SavePositionRequest
{
    /**
     * @param list<array<string, mixed>> $attributes {attributeId, required, section, sortOrder}
     * @param list<array<string, mixed>> $accessRules {attributeId, operator, value}
     * @param list<string>               $projectTags
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название позиции.')]
        #[Assert\Length(max: 180, maxMessage: 'Название не длиннее {{ limit }} символов.')]
        public readonly string $title = '',

        #[Assert\Length(max: 20000)]
        public readonly ?string $shortDescription = null,

        #[Assert\Length(max: 180)]
        public readonly ?string $company = null,

        #[Assert\Length(max: 32)]
        public readonly ?string $level = null,

        public readonly bool $public = true,

        #[Assert\PositiveOrZero(message: 'Число проектов не может быть отрицательным.')]
        #[Assert\LessThanOrEqual(50)]
        public readonly int $maxProjects = Position::DEFAULT_MAX_PROJECTS,

        #[Assert\Count(max: 100, maxMessage: 'Не больше {{ limit }} атрибутов в шаблоне.')]
        public readonly array $attributes = [],

        #[Assert\Count(max: 50, maxMessage: 'Не больше {{ limit }} правил доступа.')]
        public readonly array $accessRules = [],

        #[Assert\Count(max: 25)]
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 64)])]
        public readonly array $projectTags = [],

        /**
         * Optimistic locking. Absent on create, where there is nothing to
         * conflict with yet.
         */
        #[Assert\PositiveOrZero]
        public readonly ?int $version = null,
    ) {
    }

    /**
     * @return list<array{attributeId: int, required: bool, section: ?string, sortOrder: int}>
     */
    public function normalizedAttributes(): array
    {
        $rows = [];

        foreach ($this->attributes as $index => $row) {
            if (!\is_array($row) || !isset($row['attributeId']) || !is_numeric($row['attributeId'])) {
                continue;
            }

            $section = isset($row['section']) && \is_string($row['section']) && '' !== trim($row['section'])
                ? trim($row['section'])
                : null;

            $rows[] = [
                'attributeId' => (int) $row['attributeId'],
                'required'    => (bool) ($row['required'] ?? false),
                'section'     => $section,
                'sortOrder'   => isset($row['sortOrder']) && is_numeric($row['sortOrder'])
                    ? (int) $row['sortOrder']
                    : $index,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{attributeId: int, operator: string, value: mixed}>
     */
    public function normalizedRules(): array
    {
        $rows = [];

        foreach ($this->accessRules as $row) {
            if (!\is_array($row) || !isset($row['attributeId'], $row['operator'])) {
                continue;
            }

            if (!is_numeric($row['attributeId']) || !\is_string($row['operator'])) {
                continue;
            }

            $rows[] = [
                'attributeId' => (int) $row['attributeId'],
                'operator'    => $row['operator'],
                'value'       => $row['value'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function cleanTags(): array
    {
        $seen = [];

        foreach ($this->projectTags as $tag) {
            $name = trim((string) $tag);

            if ('' !== $name) {
                $seen[mb_strtolower($name)] ??= $name;
            }
        }

        return array_values($seen);
    }
}
