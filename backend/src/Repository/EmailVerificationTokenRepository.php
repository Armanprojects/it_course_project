<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationToken>
 */
class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    /**
     * Looks the token up by hash and loads its user in the same query: the
     * caller always needs both, and a lazy proxy would cost a second round trip.
     */
    public function findOneByPlainToken(string $plainToken): ?EmailVerificationToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->innerJoin('t.user', 'u')
            ->andWhere('t.tokenHash = :hash')
            ->setParameter('hash', EmailVerificationToken::hash($plainToken))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalidates every outstanding token of a user, so that requesting a new
     * link silently retires the previous one instead of leaving several valid.
     */
    public function invalidateAllFor(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.usedAt', ':now')
            ->andWhere('t.user = :user')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * How many links the user asked for since the given moment — the input for
     * the resend rate limit.
     */
    public function countIssuedSince(User $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.user = :user')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Housekeeping for a cron job: used and expired rows have no purpose once
     * they can no longer be redeemed.
     */
    public function deleteObsolete(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt < :before OR t.usedAt IS NOT NULL')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
