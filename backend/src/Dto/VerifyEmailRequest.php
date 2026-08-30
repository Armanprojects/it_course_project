<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\EmailVerificationToken;
use Symfony\Component\Validator\Constraints as Assert;

final class VerifyEmailRequest
{
    public function __construct(
        // Fixed length: the token is always TOKEN_BYTES rendered as hex, so a
        // malformed value is rejected before it reaches a database lookup.
        #[Assert\NotBlank(message: 'Confirmation token is required.')]
        #[Assert\Regex(
            pattern: '/^[0-9a-f]{' . 2 * EmailVerificationToken::TOKEN_BYTES . '}$/',
            message: 'This confirmation link is not valid.',
        )]
        public readonly string $token = '',
    ) {
    }
}
