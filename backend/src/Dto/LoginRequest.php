<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'This is not a valid email address.')]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Password is required.')]
        public readonly string $password = '',
    ) {
    }
}
