<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\OAuthProvider;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function emailExists(string $email): bool
    {
        $count = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findOneByIdentity(OAuthProvider $provider, string $externalId): ?User
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.identities', 'i')
            ->andWhere('i.provider = :provider')
            ->andWhere('i.externalId = :externalId')
            ->setParameter('provider', $provider)
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<User> */
    public function findByRole(UserRole $role): array
    {
        $ids = $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn(
                'SELECT id FROM "user" WHERE roles::jsonb @> :role::jsonb',
                ['role' => json_encode([$role->value])],
            );

        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByRole(UserRole $role): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT COUNT(*) FROM "user" WHERE roles::jsonb @> :role::jsonb',
                ['role' => json_encode([$role->value])],
            );
    }

    /** @return array{items: list<User>, total: int} */
    public function searchForAdmin(
        ?string $query = null,
        ?UserRole $role = null,
        ?UserStatus $status = null,
        int $page = 1,
        int $perPage = 25,
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        $query = trim((string) $query);

        if ('' !== $query) {
            $qb->andWhere('LOWER(u.email) LIKE :query')
                ->setParameter('query', '%' . $this->escapeLike(mb_strtolower($query)) . '%');
        }

        if (null !== $status) {
            $qb->andWhere('u.status = :status')->setParameter('status', $status);
        }

        if (null !== $role) {
            $ids = $this->getEntityManager()
                ->getConnection()
                ->fetchFirstColumn(
                    'SELECT id FROM "user" WHERE roles::jsonb @> :role::jsonb',
                    ['role' => json_encode([$role->value])],
                );

            if ([] === $ids) {
                return ['items' => [], 'total' => 0];
            }

            $qb->andWhere('u.id IN (:ids)')->setParameter('ids', $ids);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);

        $this->getEntityManager()->flush();
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
