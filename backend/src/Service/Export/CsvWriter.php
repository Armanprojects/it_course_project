<?php

declare(strict_types=1);

namespace App\Service\Export;

/**
 * Writes the export table as CSV, for Excel first and analysts second.
 */
final readonly class CsvWriter
{
    /**
     * Excel picks the separator from the locale, not from the file, and on a
     * Russian or German machine a comma-separated file lands entirely in
     * column A. The `sep=` hint is the documented way out and is understood by
     * Excel and LibreOffice alike; anything else reads it as a first row.
     */
    private const SEPARATOR = ';';

    /**
     * @param array{header: list<string>, rows: list<list<string>>} $table
     */
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

        // Without the BOM Excel reads the file as the system codepage and
        // mangles every Cyrillic name in it.
        return "\xEF\xBB\xBF" . $csv;
    }
}
