<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\AttributeValue;
use App\Enum\AttributeType;

final readonly class ValueFormatter
{
    public function __construct(private string $dateFormat = 'd.m.Y')
    {
    }

    public function format(?AttributeValue $value): string
    {
        if (null === $value || $value->isEmpty()) {
            return '';
        }

        return match ($value->getType()) {
            AttributeType::String  => (string) $value->getValueString(),
            AttributeType::Text    => (string) $value->getValueText(),
            AttributeType::Image   => (string) $value->getValueImageUrl(),
            AttributeType::Select  => (string) $value->getValueOption(),
            AttributeType::Numeric => $this->number((string) $value->getValueNumber()),
            AttributeType::Boolean => $value->getValueBool() ? 'да' : 'нет',
            AttributeType::Date    => $value->getValueDate()?->format($this->dateFormat) ?? '',
            AttributeType::Period  => $this->period($value),
        };
    }

    private function number(string $raw): string
    {
        if (!str_contains($raw, '.')) {
            return $raw;
        }

        return rtrim(rtrim($raw, '0'), '.');
    }

    private function period(AttributeValue $value): string
    {
        $from = $value->getValueDate()?->format($this->dateFormat);
        $to   = $value->getValueDateEnd()?->format($this->dateFormat);

        return match (true) {
            null !== $from && null !== $to => $from . ' — ' . $to,
            null !== $from                 => $from . ' — ',
            null !== $to                   => ' — ' . $to,
            default                        => '',
        };
    }
}
