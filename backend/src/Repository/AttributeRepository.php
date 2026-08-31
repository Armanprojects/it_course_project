<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attribute;
use App\Entity\AttributeValue;
use App\Entity\Profile;
use App\Enum\AttributeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribute>
 */
class AttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    /**
     * The library picker: prefix search, category filter and a cap.
     *
     * The brief asks for prefix search specifically, so the LIKE is anchored
     * ("php%") and runs on the normalised name — a leading-wildcard search
     * could not use an index at all.
     *
     * @return list<Attribute>
     */
    public function search(
        ?string $prefix = null,
        ?AttributeCategory $category = null,
        int $limit = 50,
        bool $includeRemoved = false,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit);

        // The picker must never offer a removed attribute; the management
        // screen shows them so a recruiter can restore one.
        if (!$includeRemoved) {
            $qb->andWhere('a.removedAt IS NULL');
        }

        $prefix = trim((string) $prefix);

        if ('' !== $prefix) {
            $qb->andWhere('a.nameNormalized LIKE :prefix')
                ->setParameter('prefix', $this->escapeLike(mb_strtolower($prefix)) . '%');
        }

        if (null !== $category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Attributes most recently attached to any profile, minus the ones this
     * profile already has — the "recently used" shortcut of the picker.
     *
     * Ordered by when the value was last touched, which is the closest thing
     * to "what people actually pick" that the schema records.
     *
     * @return list<Attribute>
     */
    public function findRecentlyUsed(Profile $profile, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin(AttributeValue::class, 'v', 'WITH', 'v.attribute = a')
            ->andWhere('a.removedAt IS NULL')
            ->groupBy('a.id')
            ->orderBy('MAX(v.updatedAt)', 'DESC')
            ->setMaxResults($limit);

        // NOT EXISTS rather than fetching the profile's own ids first: one
        // query instead of two, and no list to pass back in.
        $qb->andWhere($qb->expr()->notIn(
            'a.id',
            $this->getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(own.attribute)')
                ->from(AttributeValue::class, 'own')
                ->andWhere('own.profile = :profile')
                ->getDQL(),
        ))->setParameter('profile', $profile);

        return $qb->getQuery()->getResult();
    }

    /**
     * Every built-in attribute, in a stable order. These are the "Me" section:
     * they exist for every profile whether or not a value was ever entered.
     *
     * @return list<Attribute>
     */
    public function findSystem(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.system = true')
            ->andWhere('a.removedAt IS NULL')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Underscore and percent are wildcards in LIKE; a name containing one
     * would otherwise match far more than the user typed.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
