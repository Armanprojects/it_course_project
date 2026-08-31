<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * The tag cloud: the most used technology tags with their weight.
     *
     * Ordering runs on the denormalised usage_count index, which is exactly
     * why that counter exists — counting projects per tag here would scan the
     * whole project_tag table on every home page load.
     *
     * @return list<array{id: int, name: string, usageCount: int}>
     */
    public function findCloud(int $limit = 30): array
    {
        /** @var list<array{id: int, name: string, usageCount: int}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('t.id', 't.name', 't.usageCount')
            ->andWhere('t.usageCount > 0')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }

    public function findOneByName(string $name): ?Tag
    {
        return $this->findOneBy(['nameNormalized' => Tag::normalize($name)]);
    }

    /**
     * Autocomplete for the project tag input: prefix match, most-used first,
     * so that typing "re" offers the React everyone already uses rather than
     * a one-off tag someone typed once.
     *
     * @return list<array{id: int, name: string, usageCount: int}>
     */
    public function suggest(?string $prefix, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.id', 't.name', 't.usageCount')
            ->orderBy('t.usageCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults($limit);

        $prefix = trim((string) $prefix);

        if ('' !== $prefix) {
            $qb->andWhere('t.nameNormalized LIKE :prefix')
                ->setParameter(
                    'prefix',
                    str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Tag::normalize($prefix)) . '%',
                );
        }

        /** @var list<array{id: int, name: string, usageCount: int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $rows;
    }
}
