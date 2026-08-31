<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePostRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Сообщение не может быть пустым.')]
        #[Assert\Length(max: 5000, maxMessage: 'Сообщение не длиннее {{ limit }} символов.')]
        public readonly string $content = '',
    ) {
    }
}
