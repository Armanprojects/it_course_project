<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class UserAdminService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
    ) {
    }

    public function block(User $target): User
    {
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

    public function delete(User $target): void
    {
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

    public function revokeRole(User $target, UserRole $role): User
    {
        if (UserRole::Admin === $role) {
            $this->assertNotLastAdmin($target, $role);
        }

        $target->revokeRole($role);
        $this->em->flush();

        return $target;
    }

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
