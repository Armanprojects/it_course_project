<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Rejects banned accounts on every authenticated request.
 *
 * Sign-in already refuses them (AuthenticationService::assertUsable), but a
 * token handed out before the ban stays valid until it expires — without this
 * check a blocked user would keep working for up to JWT_TTL seconds. The
 * firewall runs the checker after resolving the token, so the ban takes effect
 * on the blocked user's very next request.
 *
 * Pending accounts are deliberately let through here: they cannot obtain a
 * token in the first place, and the confirmation flow issues its own.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive() && !$user->isPending()) {
            // CustomUserMessageAccountStatusException surfaces as 401 through
            // JsonAuthenticationEntryPoint, so the client drops the dead token
            // and returns to the login screen instead of looping on 403s.
            throw new CustomUserMessageAccountStatusException('This account has been blocked.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Nothing to verify after the credentials check: the ban is a property
        // of the account, and it is already handled in checkPreAuth().
    }
}
