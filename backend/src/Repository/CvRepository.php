<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AttributeValue;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Project;
use App\Enum\CvStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Cv> */
class CvRepository extends ServiceEntityRepository
{
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

    /** @return list<Cv> */
    public function search(string $query, int $limit = 50): array
    {
        $query = trim($query);

        if ('' === $query) {
            return [];
        }

        $like = '%' . mb_strtolower(
            str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query),
        ) . '%';

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
            ->setParameter('like', $like)
            ->orderBy('c.likesCount', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit);

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
        ));

        return array_values(iterator_to_array(new Paginator($qb->getQuery(), true)));
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
