<?php

declare(strict_types=1);

namespace App\Enum;


enum UserRole: string
{
    case Candidate = 'ROLE_CANDIDATE';
    case Recruiter = 'ROLE_RECRUITER';
    case Admin     = 'ROLE_ADMIN';
}
