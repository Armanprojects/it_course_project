<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Profile;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Profile> */
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

    public function findForPage(int $id): ?Profile
    {
        $profile = $this->findWithValues($id);

        if (null === $profile) {
            return null;
        }

        $this->createQueryBuilder('p')
            ->addSelect('project', 'tag')
            ->leftJoin('p.projects', 'project')
            ->leftJoin('project.tags', 'tag')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        $this->createQueryBuilder('p')
            ->addSelect('cv', 'position')
            ->leftJoin('p.cvs', 'cv')
            ->leftJoin('cv.position', 'position')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        return $profile;
    }
}
