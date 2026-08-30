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

        /**
         * Checked on the server too, not only in the browser: the client-side
         * comparison is a convenience, and the API must not accept a signup
         * whose password the user may have mistyped.
         */
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
        // Literal, not SignupRole::Candidate->value: a property default has to
        // be a constant expression, and an enum case access is not one.
        public readonly string $role = 'ROLE_CANDIDATE',
    ) {
    }

    /**
     * Safe after validation: Choice has already rejected anything that is not
     * one of the two signup roles.
     */
    public function signupRole(): SignupRole
    {
        return SignupRole::tryFrom($this->role) ?? SignupRole::Candidate;
    }
}
