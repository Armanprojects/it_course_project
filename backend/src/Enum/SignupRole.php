<?php

declare(strict_types=1);

namespace App\Enum;

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

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
