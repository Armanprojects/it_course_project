<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SaveProjectRequest
{
    /**
     * @param list<string> $tags free-form tag names; unknown ones are created
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название проекта.')]
        #[Assert\Length(max: 180, maxMessage: 'Название не длиннее {{ limit }} символов.')]
        public readonly string $name = '',

        #[Assert\Length(max: 20000, maxMessage: 'Описание не длиннее {{ limit }} символов.')]
        public readonly ?string $description = null,

        #[Assert\Date(message: 'Дата начала должна быть в формате ГГГГ-ММ-ДД.')]
        public readonly ?string $periodFrom = null,

        /**
         * Null means the project is still running — that is what makes a
         * project "ongoing", so an empty end date is valid, not missing.
         */
        #[Assert\Date(message: 'Дата окончания должна быть в формате ГГГГ-ММ-ДД.')]
        public readonly ?string $periodTo = null,

        #[Assert\Count(max: 25, maxMessage: 'Не больше {{ limit }} тегов на проект.')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(max: 64, maxMessage: 'Тег не длиннее {{ limit }} символов.'),
        ])]
        public readonly array $tags = [],
    ) {
    }

    public function periodFromDate(): ?\DateTimeImmutable
    {
        return $this->toDate($this->periodFrom);
    }

    public function periodToDate(): ?\DateTimeImmutable
    {
        return $this->toDate($this->periodTo);
    }

    /**
     * Trimmed, de-duplicated case-insensitively, blanks dropped.
     *
     * @return list<string>
     */
    public function cleanTags(): array
    {
        $seen = [];

        foreach ($this->tags as $tag) {
            $name = trim((string) $tag);

            if ('' === $name) {
                continue;
            }

            $seen[mb_strtolower($name)] ??= $name;
        }

        return array_values($seen);
    }

    private function toDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }
}
