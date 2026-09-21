<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserIdentity;

final readonly class UserSerializer
{
    /** @return array<string, mixed> */
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
            'hasPassword' => null !== $user->getPassword(),
            'emailVerifiedAt' => $user->getEmailVerifiedAt()?->format(\DATE_ATOM),
            'identities'  => array_values(array_map(
                static fn (UserIdentity $identity): string => $identity->getProvider()->value,
                $user->getIdentities()->toArray(),
            )),
        ];
    }
}
