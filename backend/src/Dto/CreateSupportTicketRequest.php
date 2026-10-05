<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateSupportTicketRequest
{
    public const PRIORITIES = ['High', 'Average', 'Low'];

    public function __construct(
        #[Assert\NotBlank(message: 'Опишите проблему.')]
        #[Assert\Length(max: 500, maxMessage: 'Описание не длиннее {{ limit }} символов.')]
        public readonly string $summary = '',

        #[Assert\Choice(choices: self::PRIORITIES, message: 'Неизвестный приоритет.')]
        public readonly string $priority = 'Average',

        // Адрес страницы, с которой открыли форму, — его составляет фронтенд.
        #[Assert\Length(max: 2000, maxMessage: 'Ссылка не длиннее {{ limit }} символов.')]
        public readonly ?string $link = null,

        // Название позиции, если тикет создан со страницы позиции или резюме.
        #[Assert\Length(max: 255, maxMessage: 'Название позиции не длиннее {{ limit }} символов.')]
        public readonly ?string $position = null,
    ) {
    }
}
