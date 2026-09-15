<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One role granted to or revoked from a user by an administrator.
 *
 * The role travels in the body rather than the URL so the same endpoint serves
 * both directions (POST grants, DELETE revokes) and the value is validated
 * against the enum instead of being pattern-matched in a route.
 */
final class ChangeUserRoleRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Role is required.')]
        #[Assert\Choice(callback: [self::class, 'roles'], message: 'Unknown role.')]
        public readonly string $role = '',
    ) {
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_column(UserRole::cases(), 'value');
    }

    public function roleEnum(): UserRole
    {
        return UserRole::from($this->role);
    }
}
