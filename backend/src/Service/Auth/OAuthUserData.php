<?php

declare(strict_types=1);

namespace App\Service\Auth;

/**
 * Provider-agnostic shape of what a social login tells us about a person.
 */
final readonly class OAuthUserData
{
    public function __construct(
        public string $externalId,
        public ?string $email,
        public ?string $name = null,
        public ?string $avatarUrl = null,
    ) {
    }
}
