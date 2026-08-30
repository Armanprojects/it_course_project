<?php

declare(strict_types=1);

namespace App\Enum;


enum AttributeType: string
{
    case String  = 'string';
    case Text    = 'text';
    case Image   = 'image';
    case Numeric = 'numeric';
    case Date    = 'date';
    case Period  = 'period';
    case Boolean = 'boolean';
    case Select  = 'select';

    public function requiresOptions(): bool
    {
        return self::Select === $this;
    }

    /**
     * @return list<FilterOperator>
     */
    public function supportedOperators(): array
    {
        return match ($this) {
            self::Numeric, self::Date => [
                FilterOperator::Equals,
                FilterOperator::NotEquals,
                FilterOperator::GreaterThan,
                FilterOperator::GreaterOrEqual,
                FilterOperator::LessThan,
                FilterOperator::LessOrEqual,
                FilterOperator::IsSet,
            ],
            self::Boolean => [
                FilterOperator::Equals,
                FilterOperator::IsSet,
            ],
            self::Select => [
                FilterOperator::Equals,
                FilterOperator::NotEquals,
                FilterOperator::In,
                FilterOperator::IsSet,
            ],
            self::String, self::Text => [
                FilterOperator::Equals,
                FilterOperator::NotEquals,
                FilterOperator::Contains,
                FilterOperator::IsSet,
            ],
            self::Period, self::Image => [
                FilterOperator::IsSet,
            ],
        };
    }

    public function supports(FilterOperator $operator): bool
    {
        return \in_array($operator, $this->supportedOperators(), true);
    }
}
