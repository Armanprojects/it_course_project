<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserIdentity;
use App\Enum\OAuthProvider;
use App\Enum\SignupRole;
use App\Exception\AuthException;
use App\Repository\UserIdentityRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Owns every way an account can come into existence or be signed into, so that
 * controllers stay thin and the "one profile per user" rule lives in one place.
 */
final readonly class AuthenticationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserIdentityRepository $identities,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * Registers a password account in the pending state: the row reserves the
     * address straight away, but no token is issued until the confirmation link
     * is opened. Every user gets a profile immediately — the profile page must
     * exist from the first login, and creating it lazily would mean guarding
     * against a missing profile in every other service.
     */
    public function register(
        string $email,
        string $plainPassword,
        SignupRole $role = SignupRole::Candidate,
    ): User {
        $email = $this->normalizeEmail($email);

        if ($this->users->emailExists($email)) {
            throw AuthException::emailAlreadyUsed();
        }

        $user = new User($email, $role->toUserRole());
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $user->markPending();

        new Profile($user);

        $this->em->persist($user);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Two registrations for the same address can pass the check above
            // concurrently; the unique index is what actually decides.
            throw AuthException::emailAlreadyUsed();
        }

        return $user;
    }

    /**
     * Verifies credentials for the login endpoint. The firewall does not do this
     * for us: /api/auth/login is deliberately outside the JWT firewall.
     */
    public function authenticate(string $email, string $plainPassword): User
    {
        $user = $this->users->findOneByEmail($this->normalizeEmail($email));

        if (null === $user) {
            // Hash anyway so that a missing account and a wrong password take
            // about the same time and cannot be told apart from the outside.
            $this->hasher->hashPassword(new User('timing@example.com'), $plainPassword);

            throw AuthException::invalidCredentials();
        }

        if (null === $user->getPassword()) {
            throw AuthException::passwordLoginUnavailable();
        }

        if (!$this->hasher->isPasswordValid($user, $plainPassword)) {
            throw AuthException::invalidCredentials();
        }

        $this->assertUsable($user);

        $user->touchLastLogin();
        $this->em->flush();

        return $user;
    }

    /**
     * Resolves the account behind a social login, creating or linking as needed.
     *
     * Three cases, in order:
     *   1. the identity is known — sign that user in;
     *   2. the email belongs to an existing account — link the identity to it,
     *      which is what lets someone register by password and later use Google;
     *   3. neither — create a fresh account without a password, with the role
     *      the visitor picked before leaving for the provider.
     */
    public function authenticateWithProvider(
        OAuthProvider $provider,
        string $externalId,
        ?string $email,
        SignupRole $role = SignupRole::Candidate,
    ): User {
        $identity = $this->identities->findOneByProviderAndExternalId($provider, $externalId);

        if (null !== $identity) {
            $user = $identity->getUser();
            $this->assertUsable($user);

            $user->touchLastLogin();
            $this->em->flush();

            return $user;
        }

        if (null === $email || '' === trim($email)) {
            throw AuthException::providerEmailMissing();
        }

        $email = $this->normalizeEmail($email);
        $user  = $this->users->findOneByEmail($email);

        if (null === $user) {
            $user = new User($email, $role->toUserRole());
            // The provider already proved the address belongs to this person,
            // so there is nothing left for a confirmation link to establish.
            $user->verifyEmail();
            new Profile($user);
            $this->em->persist($user);
        } else {
            // The role only applies to a brand new account: signing into an
            // existing one through a provider must not change its privileges.
            $this->assertUsable($user);
        }

        $user->addIdentity(new UserIdentity($user, $provider, $externalId));
        $user->touchLastLogin();

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw AuthException::identityTakenByAnotherUser();
        }

        return $user;
    }

    /**
     * Blocked accounts must not get a token: the ban has to bite at sign-in,
     * not only on the next request. An unconfirmed signup is told apart from a
     * ban so the UI can offer to resend the link instead of a dead end.
     */
    private function assertUsable(User $user): void
    {
        if ($user->isPending()) {
            throw AuthException::emailNotVerified();
        }

        if (!$user->isActive()) {
            throw AuthException::accountBlocked();
        }
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
