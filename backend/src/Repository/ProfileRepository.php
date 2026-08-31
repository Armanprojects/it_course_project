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

    /**
     * The whole profile page in a fixed number of queries.
     *
     * Deliberately three separate fetches rather than one big join: collecting
     * values, projects and CVs in a single query would multiply the rows by
     * each collection's size. Doctrine stitches them onto the same managed
     * entity, so the later calls only fill in what the first one left lazy.
     */
    public function findForPage(int $id): ?Profile
    {
        $profile = $this->findWithValues($id);

        if (null === $profile) {
            return null;
        }

        // Tags come along: the project list renders them, and without this
        // each project would fetch its own tag collection.
        $this->createQueryBuilder('p')
            ->addSelect('project', 'tag')
            ->leftJoin('p.projects', 'project')
            ->leftJoin('project.tags', 'tag')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

        // Same for the CV rows, which each name their position.
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
