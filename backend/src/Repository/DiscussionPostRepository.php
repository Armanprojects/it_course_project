<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiscussionPost>
 */
class DiscussionPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionPost::class);
    }

    /**
     * Messages of one discussion, oldest first.
     *
     * `$after` makes this the polling endpoint too: the client asks for
     * "anything newer than the last id I hold", which costs an index lookup
     * rather than re-sending the whole thread every few seconds.
     *
     * @return list<DiscussionPost>
     */
    public function findForPosition(Position $position, ?int $after = null, int $limit = 200): array
    {
        // The profile comes along because a recruiter's view links the author's
        // name to it; fetching it lazily would be a query per message.
        $qb = $this->createQueryBuilder('p')
            ->addSelect('author', 'authorProfile')
            ->leftJoin('p.author', 'author')
            ->leftJoin('author.profile', 'authorProfile')
            ->andWhere('p.position = :position')
            ->setParameter('position', $position)
            // Ordered by id, not timestamp: new messages only ever append, and
            // two posts in the same second must still have a stable order.
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $after) {
            $qb->andWhere('p.id > :after')->setParameter('after', $after);
        }

        return $qb->getQuery()->getResult();
    }
}
