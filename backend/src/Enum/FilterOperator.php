<?php

declare(strict_types=1);

namespace App\Enum;


enum FilterOperator: string
{
    case Equals       = 'eq';
    case NotEquals    = 'neq';
    case GreaterThan  = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan     = 'lt';
    case LessOrEqual  = 'lte';
    case Contains     = 'contains';
    case In           = 'in';
    case IsSet        = 'is_set';
}
