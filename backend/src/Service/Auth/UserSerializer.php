<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserIdentity;

/**
 * The single definition of what a User looks like over the wire, so that /login,
 * /register and /me can never drift apart.
 */
final readonly class UserSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(User $user): array
    {
        return [
            'id'          => $user->getId(),
            'email'       => $user->getEmail(),
            'roles'       => $user->getRoles(),
            'status'      => $user->getStatus()->value,
            'locale'      => $user->getLocale()->value,
            'theme'       => $user->getTheme()->value,
            'createdAt'   => $user->getCreatedAt()->format(\DATE_ATOM),
            'lastLoginAt' => $user->getLastLoginAt()?->format(\DATE_ATOM),
            'profileId'   => $user->getProfile()?->getId(),
            // Lets the UI show which providers are linked and offer to add the
            // missing ones, and tells it whether a password is set at all.
            'hasPassword' => null !== $user->getPassword(),
            'emailVerifiedAt' => $user->getEmailVerifiedAt()?->format(\DATE_ATOM),
            'identities'  => array_values(array_map(
                static fn (UserIdentity $identity): string => $identity->getProvider()->value,
                $user->getIdentities()->toArray(),
            )),
        ];
    }
}
