<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserIdentity;
use App\Enum\OAuthProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserIdentity>
 */
class UserIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserIdentity::class);
    }

    public function findOneByProviderAndExternalId(OAuthProvider $provider, string $externalId): ?UserIdentity
    {
        return $this->findOneBy([
            'provider'   => $provider,
            'externalId' => $externalId,
        ]);
    }

    public function findOneForUser(User $user, OAuthProvider $provider): ?UserIdentity
    {
        return $this->findOneBy([
            'user'     => $user,
            'provider' => $provider,
        ]);
    }
}
