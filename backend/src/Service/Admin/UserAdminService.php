<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Administration of user accounts: blocking, deletion and role changes.
 *
 * An administrator may act on their own account — revoke their own admin role
 * (the brief says so outright) and block or delete themselves. Both take
 * effect at once: App\Security\UserChecker rejects a blocked account on its
 * next request, and a demoted admin loses the admin screens as soon as the
 * client re-reads the user.
 *
 * The one thing still held back is emptying the platform of administrators:
 * with the last one gone, nobody could restore the role.
 */
final readonly class UserAdminService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
    ) {
    }

    public function block(User $target): User
    {
        // Blocking yourself is allowed: it ends your own session on the next
        // request (App\Security\UserChecker), and any other administrator can
        // lift the block afterwards.
        $target->block();
        $this->em->flush();

        return $target;
    }

    public function unblock(User $target): User
    {
        $target->unblock();
        $this->em->flush();

        return $target;
    }

    /**
     * Deleting a user takes their profile, CVs and positions with them: the
     * mappings cascade, which is what the brief means by "delete".
     */
    public function delete(User $target): void
    {
        // Deleting your own account is not forbidden either — only leaving the
        // platform without administrators is, and the check below covers that
        // case whether the target is oneself or somebody else.
        $this->assertNotLastAdmin($target, UserRole::Admin);

        $this->em->remove($target);
        $this->em->flush();
    }

    public function grantRole(User $target, UserRole $role): User
    {
        $target->grantRole($role);
        $this->em->flush();

        return $target;
    }

    /**
     * Revoking a role, including the actor's own admin role — explicitly
     * permitted by the brief. The client is told to re-read its own user
     * afterwards, since an admin who just demoted themselves keeps a token
     * whose payload still claims the role.
     */
    public function revokeRole(User $target, UserRole $role): User
    {
        if (UserRole::Admin === $role) {
            $this->assertNotLastAdmin($target, $role);
        }

        $target->revokeRole($role);
        $this->em->flush();

        return $target;
    }

    /**
     * Guards the one irreversible mistake: removing the only administrator.
     * Applies to demotion and deletion alike — both end with no admin left.
     */
    private function assertNotLastAdmin(User $target, UserRole $role): void
    {
        if (!$target->hasRole($role)) {
            return;
        }

        if ($this->users->countByRole($role) <= 1) {
            throw new ConflictHttpException(
                'This is the last administrator: grant the role to someone else first.',
            );
        }
    }
}
