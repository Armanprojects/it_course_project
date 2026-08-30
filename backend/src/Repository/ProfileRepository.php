<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Profile>
 */
class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function findOneByUser(User $user): ?Profile
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Loads the profile with everything the profile page renders at once, so the
     * page costs a couple of queries instead of one per attribute value.
     */
    public function findWithValues(int $id): ?Profile
    {
        return $this->createQueryBuilder('p')
            ->addSelect('v', 'a')
            ->leftJoin('p.attributeValues', 'v')
            ->leftJoin('v.attribute', 'a')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
