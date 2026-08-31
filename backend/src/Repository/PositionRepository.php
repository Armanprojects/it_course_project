<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionAttribute;
use App\Enum\CvStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE     = 100;

    /**
     * Whitelist of sortable columns. The client sends a key, never a column
     * name — passing user input straight into ORDER BY would be an injection.
     */
    private const SORTABLE = [
        'title'     => 'p.title',
        'company'   => 'p.company',
        'level'     => 'p.level',
        'createdAt' => 'p.createdAt',
        'updatedAt' => 'p.updatedAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * One page of the positions table, plus the total for the pager.
     *
     * The submitted-CV counter is fetched for the whole page in a single
     * grouped query instead of per row, so the listing costs a fixed number of
     * queries no matter how many positions it shows.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int, pages: int}
     */
    public function findPage(
        ?string $search = null,
        string $sort = 'updatedAt',
        string $direction = 'desc',
        int $page = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
    ): array {
        $page     = max(1, $page);
        $pageSize = min(max(1, $pageSize), self::MAX_PAGE_SIZE);

        $qb = $this->createQueryBuilder('p');
        $this->applySearch($qb, $search);

        $column = self::SORTABLE[$sort] ?? self::SORTABLE['updatedAt'];

        $qb->orderBy($column, 'asc' === mb_strtolower($direction) ? 'ASC' : 'DESC')
            // Second key keeps paging stable when the sorted column ties.
            ->addOrderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($qb->getQuery(), false);
        $total     = \count($paginator);

        /** @var list<Position> $positions */
        $positions = array_values(iterator_to_array($paginator));

        $pages = $total > 0 ? (int) ceil($total / $pageSize) : 1;

        return [
            'items'    => $this->toRows($positions),
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'pages'    => $pages,
        ];
    }

    /**
     * Most recently created or updated positions, for the home page table.
     *
     * @return list<array<string, mixed>>
     */
    public function findLatest(int $limit = 5): array
    {
        /** @var list<Position> $positions */
        $positions = $this->createQueryBuilder('p')
            ->orderBy('p.updatedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->toRows($positions);
    }

    /**
     * Top positions by number of submitted CVs.
     *
     * Grouped and ordered in the database: ranking this in PHP would mean
     * loading every position with all of its CVs just to keep five rows.
     *
     * @return list<array<string, mixed>>
     */
    public function findMostPopular(int $limit = 5): array
    {
        /** @var list<array{0: Position, cvCount: int|string}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('p', 'COUNT(c.id) AS cvCount')
            ->innerJoin('p.cvs', 'c', 'WITH', 'c.status = :published')
            ->setParameter('published', CvStatus::Published)
            ->groupBy('p.id')
            ->orderBy('cvCount', 'DESC')
            ->addOrderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        /** @var list<Position> $positions */
        $positions = [];
        $cvCounts  = [];

        foreach ($rows as $row) {
            $positions[]                        = $row[0];
            $cvCounts[$row[0]->getId()]         = (int) $row['cvCount'];
        }

        $attributes = $this->countAttributesFor($positions);
        $items      = [];

        foreach ($positions as $position) {
            $items[] = $this->toRow(
                $position,
                $cvCounts[$position->getId()] ?? 0,
                $attributes[$position->getId()] ?? 0,
            );
        }

        return $items;
    }

    /**
     * Read-only detail of a single position: the attributes its template is
     * made of, joined in one go so rendering them costs no extra query.
     */
    public function findDetail(int $id): ?Position
    {
        return $this->createQueryBuilder('p')
            ->addSelect('link', 'attribute', 'tag')
            ->leftJoin('p.attributes', 'link')
            ->leftJoin('link.attribute', 'attribute')
            ->leftJoin('p.projectTags', 'tag')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<Position> $positions
     *
     * @return list<array<string, mixed>>
     */
    private function toRows(array $positions): array
    {
        $counts     = $this->countPublishedCvsFor($positions);
        $attributes = $this->countAttributesFor($positions);
        $rows       = [];

        foreach ($positions as $position) {
            $rows[] = $this->toRow(
                $position,
                $counts[$position->getId()] ?? 0,
                $attributes[$position->getId()] ?? 0,
            );
        }

        return $rows;
    }

    /**
     * Active template-attribute counts keyed by position id.
     *
     * Counted in one grouped query: reading $position->getAttributes() per row
     * would lazy-load a collection per position — a query inside a loop.
     *
     * @param list<Position> $positions
     *
     * @return array<int, int>
     */
    private function countAttributesFor(array $positions): array
    {
        $ids = $this->idsOf($positions);

        if ([] === $ids) {
            return [];
        }

        /** @var list<array{positionId: int|string, attributeCount: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(link.position) AS positionId', 'COUNT(link.id) AS attributeCount')
            ->from(PositionAttribute::class, 'link')
            ->andWhere('link.position IN (:ids)')
            ->andWhere('link.removedAt IS NULL')
            ->setParameter('ids', $ids)
            ->groupBy('positionId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['positionId']] = (int) $row['attributeCount'];
        }

        return $counts;
    }

    /**
     * @param list<Position> $positions
     *
     * @return list<int>
     */
    private function idsOf(array $positions): array
    {
        $ids = [];

        foreach ($positions as $position) {
            if (null !== $position->getId()) {
                $ids[] = $position->getId();
            }
        }

        return $ids;
    }

    /**
     * Submitted CV counts keyed by position id, for a whole batch of positions.
     *
     * @param list<Position> $positions
     *
     * @return array<int, int>
     */
    private function countPublishedCvsFor(array $positions): array
    {
        $ids = $this->idsOf($positions);

        if ([] === $ids) {
            return [];
        }

        /** @var list<array{positionId: int|string, cvCount: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.position) AS positionId', 'COUNT(c.id) AS cvCount')
            ->from(Cv::class, 'c')
            ->andWhere('c.position IN (:ids)')
            ->andWhere('c.status = :published')
            ->setParameter('ids', $ids)
            ->setParameter('published', CvStatus::Published)
            ->groupBy('positionId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['positionId']] = (int) $row['cvCount'];
        }

        return $counts;
    }

    /**
     * Full-text search over title, company and description, backed by the
     * PostgreSQL tsvector index on the table.
     *
     * The trailing ILIKE keeps a half-typed word like "devo" matching, which a
     * tsquery alone would not: the index stores whole lexemes.
     */
    private function applySearch(QueryBuilder $qb, ?string $search): void
    {
        $search = trim((string) $search);

        if ('' === $search) {
            return;
        }

        $tsquery = $this->toPrefixQuery($search);

        if ('' !== $tsquery) {
            $qb->andWhere('TS_MATCH(p.searchVector, :query) = TRUE OR LOWER(p.title) LIKE :prefix OR LOWER(p.company) LIKE :prefix')
                ->setParameter('query', $tsquery);
        } else {
            $qb->andWhere('LOWER(p.title) LIKE :prefix OR LOWER(p.company) LIKE :prefix');
        }

        $qb->setParameter('prefix', '%' . mb_strtolower($search) . '%');
    }

    /**
     * Turns free-form input into a prefix tsquery: each word becomes "word:*"
     * and the words are ANDed, so "senior back" narrows instead of widening.
     */
    private function toPrefixQuery(string $search): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $search, -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $words || [] === $words) {
            return '';
        }

        return implode(' & ', array_map(static fn (string $word): string => $word . ':*', $words));
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Position $position, int $cvCount, int $attributeCount): array
    {
        return [
            'id'               => $position->getId(),
            'title'            => $position->getTitle(),
            'shortDescription' => $position->getShortDescription(),
            'company'          => $position->getCompany(),
            'level'            => $position->getLevel(),
            'public'           => $position->isPublic(),
            'attributeCount'   => $attributeCount,
            'cvCount'          => $cvCount,
            'createdAt'        => $position->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt'        => $position->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    public function save(Position $position, bool $flush = true): void
    {
        $this->getEntityManager()->persist($position);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
