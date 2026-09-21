<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\AttributeValue;
use App\Enum\AttributeType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class AttributeValueWriter
{
    /** @param mixed $value scalar for most types, {from,to} for a period */
    public function write(AttributeValue $target, mixed $value): void
    {
        if (null === $value || '' === $value) {
            $target->clear();

            return;
        }

        match ($target->getType()) {
            AttributeType::String  => $target->setValueString($this->asString($target, $value, 255)),
            AttributeType::Text    => $target->setValueText($this->asString($target, $value, 65535)),
            AttributeType::Image   => $target->setValueImageUrl($this->asUrl($target, $value)),
            AttributeType::Numeric => $target->setValueNumber($this->asNumber($target, $value)),
            AttributeType::Date    => $target->setValueDate($this->asDate($target, $value)),
            AttributeType::Boolean => $target->setValueBool($this->asBool($value)),
            AttributeType::Select  => $target->setValueOption($this->asOption($target, $value)),
            AttributeType::Period  => $this->writePeriod($target, $value),
        };
    }

    private function writePeriod(AttributeValue $target, mixed $value): void
    {
        if (!\is_array($value)) {
            throw $this->invalid($target, 'ожидается объект {from, to}.');
        }

        $from = $value['from'] ?? null;
        $to   = $value['to'] ?? null;

        try {
            $target->setPeriod(
                null === $from || '' === $from ? null : $this->asDate($target, $from),
                null === $to || '' === $to ? null : $this->asDate($target, $to),
            );
        } catch (\InvalidArgumentException $e) {
            throw $this->invalid($target, $e->getMessage());
        }
    }

    private function asString(AttributeValue $target, mixed $value, int $max): string
    {
        if (!\is_string($value) && !\is_numeric($value)) {
            throw $this->invalid($target, 'ожидается строка.');
        }

        $string = trim((string) $value);

        if (mb_strlen($string) > $max) {
            throw $this->invalid($target, sprintf('длина не должна превышать %d символов.', $max));
        }

        return $string;
    }

    private function asUrl(AttributeValue $target, mixed $value): string
    {
        $url = $this->asString($target, $value, 1024);

        if (!filter_var($url, \FILTER_VALIDATE_URL)) {
            throw $this->invalid($target, 'ожидается корректная ссылка.');
        }

        $scheme = mb_strtolower((string) parse_url($url, \PHP_URL_SCHEME));

        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw $this->invalid($target, 'ссылка должна начинаться с http:// или https://.');
        }

        return $url;
    }

    private function asNumber(AttributeValue $target, mixed $value): string
    {
        if (!is_numeric($value)) {
            throw $this->invalid($target, 'ожидается число.');
        }

        return (string) $value;
    }

    private function asDate(AttributeValue $target, mixed $value): \DateTimeImmutable
    {
        if (!\is_string($value)) {
            throw $this->invalid($target, 'ожидается дата в формате ГГГГ-ММ-ДД.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw $this->invalid($target, 'ожидается дата в формате ГГГГ-ММ-ДД.');
        }

        return $date;
    }

    private function asBool(mixed $value): bool
    {
        return filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function asOption(AttributeValue $target, mixed $value): string
    {
        $option = $this->asString($target, $value, 255);

        if (!$target->getAttribute()->hasOption($option)) {
            throw $this->invalid($target, sprintf('значение "%s" отсутствует в списке.', $option));
        }

        return $option;
    }

    private function invalid(AttributeValue $target, string $reason): BadRequestHttpException
    {
        return new BadRequestHttpException(sprintf(
            'Атрибут «%s»: %s',
            $target->getAttribute()->getName(),
            $reason,
        ));
    }
}
