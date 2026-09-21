<?php

declare(strict_types=1);

namespace App\Service\Export;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

/**
 * Renders a QR code as plain HTML — a grid of absolutely positioned squares.
 *
 * The usual route is a PNG, but that needs ext-gd, and an SVG goes through
 * Dompdf's SVG parser, which rasterises inconsistently. Squares are the one
 * shape every HTML renderer draws exactly, so the printed code scans reliably
 * and the runtime image needs no extra extension.
 */
final readonly class QrCodeRenderer
{
    public function __construct(private int $sizeMm = 26)
    {
    }

    public function toHtml(string $data): string
    {
        // A printed code is often scanned from a slightly crumpled page, and
        // medium correction recovers ~15% of it; the payload is a short URL,
        // so the extra redundancy costs no meaningful density.
        $matrix = (new MatrixFactory())->create(new QrCode(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 0,
        ));

        $blocks = $matrix->getBlockCount();
        // Rounded down so accumulated fractions cannot push the last column
        // past the container and clip it.
        $block  = floor(($this->sizeMm / $blocks) * 1000) / 1000;
        $side   = $block * $blocks;

        $html = \sprintf(
            '<div style="position:relative;width:%1$smm;height:%1$smm;background:#ffffff;">',
            $this->mm($side),
        );

        for ($row = 0; $row < $blocks; ++$row) {
            for ($column = 0; $column < $blocks; ++$column) {
                if (1 !== $matrix->getBlockValue($row, $column)) {
                    continue;
                }

                $html .= \sprintf(
                    '<span style="position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;background:#000000;"></span>',
                    $this->mm($column * $block),
                    $this->mm($row * $block),
                    // Overlap of a hair: adjacent squares rounded to device
                    // pixels would otherwise leave white seams that break the
                    // scan on darker printers.
                    $this->mm($block * 1.02),
                    $this->mm($block * 1.02),
                );
            }
        }

        return $html . '</div>';
    }

    private function mm(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
