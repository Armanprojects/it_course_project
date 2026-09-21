<?php

declare(strict_types=1);

namespace App\Service\Export;

final readonly class CsvWriter
{
    private const SEPARATOR = ';';

    /** @param array{header: list<string>, rows: list<list<string>>} $table */
    public function write(array $table): string
    {
        $handle = fopen('php://temp', 'r+b');

        if (false === $handle) {
            throw new \RuntimeException('Не удалось открыть буфер для CSV.');
        }

        fwrite($handle, 'sep=' . self::SEPARATOR . "\r\n");

        fputcsv($handle, $table['header'], self::SEPARATOR, '"', '');

        foreach ($table['rows'] as $row) {
            fputcsv($handle, $row, self::SEPARATOR, '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . $csv;
    }
}
