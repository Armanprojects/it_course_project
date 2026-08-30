<?php

declare(strict_types=1);

namespace App\Enum;


/**
 * Роли, которые пользователь может выбрать себе сам при регистрации.
 *
 * Отдельный enum, а не UserRole: администратора нельзя назначить себе через
 * публичный эндпоинт, и здесь это гарантируется типом, а не проверкой,
 * которую можно забыть добавить в новом месте.
 */
enum SignupRole: string
{
    case Candidate = 'ROLE_CANDIDATE';
    case Recruiter = 'ROLE_RECRUITER';

    public function toUserRole(): UserRole
    {
        return match ($this) {
            self::Candidate => UserRole::Candidate,
            self::Recruiter => UserRole::Recruiter,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
