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

/**
 * @extends ServiceEntityRepository<Cv>
 */
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

    /**
     * CVs created in the last N hours — the "10 new CVs today" figure the home
     * page shows to anonymous visitors. Runs on idx_cv_created_at.
     */
    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * CVs submitted to one position — the recruiter's view of who applied.
     *
     * Only published ones: a draft is the candidate's private work in
     * progress, and the brief makes publishing the act that reveals it.
     *
     * @return list<Cv>
     */
    public function findForPosition(Position $position, bool $publishedOnly = true): array
    {
        // The name shown in each row is built from the profile's own attribute
        // values, and "liked by me" reads the likes — both are joined in so a
        // table of CVs costs a fixed number of queries instead of three per row.
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
     * Full-text search across submitted CVs, for recruiters.
     *
     * The haystack is the candidate's own text — their profile values and
     * project descriptions — joined against the position title, so a recruiter
     * can look for "kubernetes" and find whoever wrote it anywhere.
     *
     * Runs as one query with EXISTS subqueries rather than loading candidates
     * and filtering in PHP.
     *
     * @return list<Cv>
     */
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
            // Same reason as findForPosition: the row renders a name built from
            // attribute values and a "liked by me" flag.
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

        // Technology tags count as searchable text too: the home page's tag
        // cloud sends recruiters here, so "Kubernetes" must find the people
        // who tagged a project with it.
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

        // Paginator, not a bare setMaxResults: the collection joins above
        // multiply the rows, and a plain LIMIT would cut them mid-entity and
        // return fewer CVs than asked for.
        return array_values(iterator_to_array(new Paginator($qb->getQuery(), true)));
    }

    /**
     * One CV with everything its page renders, in a single query.
     */
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
