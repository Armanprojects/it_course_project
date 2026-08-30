<?php

declare(strict_types=1);

namespace App\Enum;


enum UserStatus: string
{
    /** Registered by email, waiting for the confirmation link to be opened. */
    case Pending = 'pending';
    case Active  = 'active';
    case Blocked = 'blocked';
}
