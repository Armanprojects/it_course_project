<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attribute;
use App\Entity\AttributeValue;
use App\Entity\Profile;
use App\Enum\AttributeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Attribute> */
class AttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    /** @return list<Attribute> */
    public function search(
        ?string $prefix = null,
        ?AttributeCategory $category = null,
        int $limit = 50,
        bool $includeRemoved = false,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit);

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

    /** @return list<Attribute> */
    public function findRecentlyUsed(Profile $profile, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin(AttributeValue::class, 'v', 'WITH', 'v.attribute = a')
            ->andWhere('a.removedAt IS NULL')
            ->groupBy('a.id')
            ->orderBy('MAX(v.updatedAt)', 'DESC')
            ->setMaxResults($limit);

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

    /** @return list<Attribute> */
    public function findSystem(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.system = true')
            ->andWhere('a.removedAt IS NULL')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
