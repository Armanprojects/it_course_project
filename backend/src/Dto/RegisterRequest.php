<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\SignupRole;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'This is not a valid email address.')]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(
            min: 8,
            max: 4096,
            minMessage: 'Password must be at least {{ limit }} characters long.',
        )]
        public readonly string $password = '',

        #[Assert\NotBlank(message: 'Confirm your password.')]
        #[Assert\EqualTo(
            propertyPath: 'password',
            message: 'Passwords do not match.',
        )]
        public readonly string $passwordConfirmation = '',

        #[Assert\Choice(
            callback: [SignupRole::class, 'values'],
            message: 'Choose either "ROLE_CANDIDATE" or "ROLE_RECRUITER".',
        )]

        public readonly string $role = 'ROLE_CANDIDATE',
    ) {
    }

    public function signupRole(): SignupRole
    {
        return SignupRole::tryFrom($this->role) ?? SignupRole::Candidate;
    }
}
