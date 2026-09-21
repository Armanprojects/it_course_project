<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscussionPost> */
class DiscussionPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionPost::class);
    }

    /** @return list<DiscussionPost> */
    public function findForPosition(Position $position, ?int $after = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('author', 'authorProfile')
            ->leftJoin('p.author', 'author')
            ->leftJoin('author.profile', 'authorProfile')
            ->andWhere('p.position = :position')
            ->setParameter('position', $position)

            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $after) {
            $qb->andWhere('p.id > :after')->setParameter('after', $after);
        }

        return $qb->getQuery()->getResult();
    }
}
