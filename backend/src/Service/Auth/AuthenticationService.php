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

final readonly class AuthenticationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserIdentityRepository $identities,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

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
            throw AuthException::emailAlreadyUsed();
        }

        return $user;
    }

    public function authenticate(string $email, string $plainPassword): User
    {
        $user = $this->users->findOneByEmail($this->normalizeEmail($email));

        if (null === $user) {
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

            $user->verifyEmail();
            new Profile($user);
            $this->em->persist($user);
        } else {
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
