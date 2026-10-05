<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class SalesforceExportRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите имя.')]
        #[Assert\Length(max: 40, maxMessage: 'Имя не длиннее {{ limit }} символов.')]
        public readonly string $firstName = '',

        #[Assert\NotBlank(message: 'Укажите фамилию.')]
        #[Assert\Length(max: 80, maxMessage: 'Фамилия не длиннее {{ limit }} символов.')]
        public readonly string $lastName = '',

        #[Assert\Length(max: 255, maxMessage: 'Название компании не длиннее {{ limit }} символов.')]
        public readonly ?string $company = null,

        #[Assert\Length(max: 40, maxMessage: 'Телефон не длиннее {{ limit }} символов.')]
        public readonly ?string $phone = null,

        #[Assert\Length(max: 128, maxMessage: 'Должность не длиннее {{ limit }} символов.')]
        public readonly ?string $jobTitle = null,

        #[Assert\Length(max: 2000, maxMessage: 'Комментарий не длиннее {{ limit }} символов.')]
        public readonly ?string $notes = null,

        // Администратор может завести в CRM другого пользователя;
        // для владельца профиля поле не передаётся.
        #[Assert\Positive]
        public readonly ?int $userId = null,
    ) {
    }

    public function trimmedOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
