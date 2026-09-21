<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangeUserRoleRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Role is required.')]
        #[Assert\Choice(callback: [self::class, 'roles'], message: 'Unknown role.')]
        public readonly string $role = '',
    ) {
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_column(UserRole::cases(), 'value');
    }

    public function roleEnum(): UserRole
    {
        return UserRole::from($this->role);
    }
}
