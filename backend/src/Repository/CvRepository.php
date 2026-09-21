<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeValue;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Project;
use App\Enum\CvStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Cv> */
class CvRepository extends ServiceEntityRepository
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE     = 100;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cv::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :published')
            ->setParameter('published', CvStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Cv> */
    public function findForPosition(Position $position, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('profile', 'user', 'value', 'attribute', 'likes')
            ->join('c.profile', 'profile')
            ->join('profile.user', 'user')
            ->leftJoin('profile.attributeValues', 'value')
            ->leftJoin('value.attribute', 'attribute')
            ->leftJoin('c.likes', 'likes')
            ->leftJoin('likes.recruiter', 'liker')
            ->addSelect('liker')
            ->andWhere('c.position = :position')
            ->setParameter('position', $position)
            ->orderBy('c.likesCount', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('c.status = :published')
                ->setParameter('published', CvStatus::Published);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Страница резюме для рекрутёра: полнотекстовый поиск и обычный просмотр
     * каталога — это одна и та же выборка.
     *
     * Пустой запрос не значит "ничего не найдено", он значит "фильтра нет",
     * поэтому страница открывается списком всех опубликованных резюме, а не
     * пустым экраном.
     *
     * @return array{items: list<Cv>, total: int, page: int, pageSize: int, pages: int}
     */
    public function searchPage(string $query = '', int $page = 1, int $pageSize = self::DEFAULT_PAGE_SIZE): array
    {
        $page     = max(1, $page);
        $pageSize = min(max(1, $pageSize), self::MAX_PAGE_SIZE);

        $qb = $this->createQueryBuilder('c')
            ->addSelect('profile', 'user', 'position', 'value', 'attribute', 'likes', 'liker')
            ->join('c.profile', 'profile')
            ->join('profile.user', 'user')
            ->join('c.position', 'position')
            ->leftJoin('profile.attributeValues', 'value')
            ->leftJoin('value.attribute', 'attribute')
            ->leftJoin('c.likes', 'likes')
            ->leftJoin('likes.recruiter', 'liker')
            ->andWhere('c.status = :published')
            ->setParameter('published', CvStatus::Published)
            ->orderBy('c.likesCount', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            // Разрешающий ключ: без него две записи с одинаковыми лайками и
            // временем правки могут разойтись по страницам в разном порядке.
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($pageSize);

        $this->applySearch($qb, $query);

        // fetchJoinCollection: в запросе фетч-джойнятся коллекции, и без него
        // LIMIT резал бы строки результата, а не сами резюме.
        $paginator = new Paginator($qb->getQuery(), true);
        $total     = \count($paginator);
        $pages     = $total > 0 ? (int) ceil($total / $pageSize) : 1;

        // Запрошенную страницу прижимаем к последней существующей: ссылка на
        // ?page=99 должна показать конец списка, а не пустую таблицу с
        // подписью "резюме пока нет" при непустом каталоге.
        $page = min($page, $pages);

        $paginator->getQuery()->setFirstResult(($page - 1) * $pageSize);

        /** @var list<Cv> $items */
        $items = array_values(iterator_to_array($paginator));

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'pages'    => $pages,
        ];
    }

    /**
     * Стог сена — собственный текст кандидата: значения профиля и описания
     * проектов, плюс заголовок позиции и адрес. Одним запросом с EXISTS, а не
     * загрузкой кандидатов с фильтрацией в PHP.
     */
    private function applySearch(QueryBuilder $qb, string $query): void
    {
        $query = trim($query);

        if ('' === $query) {
            return;
        }

        $like = '%' . mb_strtolower(
            str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query),
        ) . '%';

        $values = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(AttributeValue::class, 'v')
            ->andWhere('v.profile = profile')
            ->andWhere(
                'LOWER(v.valueString) LIKE :like'
                . ' OR LOWER(v.valueText) LIKE :like'
                . ' OR LOWER(v.valueOption) LIKE :like',
            )
            ->getDQL();

        $projects = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Project::class, 'pr')
            ->leftJoin('pr.tags', 'prTag')
            ->andWhere('pr.profile = profile')
            ->andWhere(
                'LOWER(pr.name) LIKE :like'
                . ' OR LOWER(pr.description) LIKE :like'
                . ' OR LOWER(prTag.name) LIKE :like',
            )
            ->getDQL();

        $qb->andWhere(sprintf(
            'LOWER(position.title) LIKE :like OR LOWER(user.email) LIKE :like'
            . ' OR EXISTS (%s) OR EXISTS (%s)',
            $values,
            $projects,
        ))->setParameter('like', $like);
    }


    public function findDetail(int $id): ?Cv
    {
        return $this->createQueryBuilder('c')
            ->addSelect('profile', 'user', 'position', 'link', 'attribute')
            ->join('c.profile', 'profile')
            ->join('profile.user', 'user')
            ->join('c.position', 'position')
            ->leftJoin('position.attributes', 'link', 'WITH', 'link.removedAt IS NULL')
            ->leftJoin('link.attribute', 'attribute')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->orderBy('link.sortOrder', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
