<?php

declare(strict_types=1);

namespace App\Service\Export;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

final readonly class QrCodeRenderer
{
    public function __construct(private int $sizeMm = 26)
    {
    }

    public function toHtml(string $data): string
    {
        $matrix = (new MatrixFactory())->create(new QrCode(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 0,
        ));

        $blocks = $matrix->getBlockCount();

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
