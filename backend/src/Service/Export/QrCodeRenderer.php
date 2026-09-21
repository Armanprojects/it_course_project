<?php

declare(strict_types=1);

namespace App\Service\Export;

use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;

/**
 * Рисует QR-код как сетку HTML-квадратов.
 *
 * Обычный путь — PNG, но он требует ext-gd, которого нет в рантайм-образе, а
 * SVG идёт через парсер Dompdf и растрируется неровно. Квадрат — единственная
 * фигура, которую любой рендерер рисует точно.
 */
final readonly class QrCodeRenderer
{
    /** Тихая зона по стандарту ISO/IEC 18004. */
    private const QUIET_MODULES = 4;

    /**
     * Размер модуля задаётся в целых пунктах, а не в миллиметрах.
     *
     * Dompdf работает в пунктах (1pt = 1/72"), и дробный модуль давал дробные
     * координаты: края соседних модулей округлялись при растеризации в разные
     * стороны. Целая сетка снимает вопрос, а 3pt дают целое число пикселей на
     * обычных разрешениях печати (4px при 96 dpi, 5px при 120 dpi).
     */
    public function __construct(private int $modulePt = 3)
    {
    }

    public function toHtml(string $data): string
    {
        // Коррекция уровня M восстанавливает ~15% кода: печатная страница
        // мнётся и пачкается, а запас на короткой ссылке почти бесплатен.
        $matrix = (new MatrixFactory())->create(new QrCode(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 0,
        ));

        $blocks = $matrix->getBlockCount();
        $quiet  = $this->modulePt * self::QUIET_MODULES;
        $side   = $this->modulePt * $blocks + 2 * $quiet;

        $html = \sprintf(
            '<div style="position:relative;width:%1$dpt;height:%1$dpt;background:#ffffff;">',
            $side,
        );

        for ($row = 0; $row < $blocks; ++$row) {
            for ($column = 0; $column < $blocks; ++$column) {
                if (1 !== $matrix->getBlockValue($row, $column)) {
                    continue;
                }

                // Встык, без перехлёста: на целой сетке швов не возникает, а
                // перехлёст больше ~5% сливает соседние модули.
                $html .= \sprintf(
                    '<span style="position:absolute;left:%1$dpt;top:%2$dpt;width:%3$dpt;height:%3$dpt;background:#000000;"></span>',
                    $quiet + $column * $this->modulePt,
                    $quiet + $row * $this->modulePt,
                    $this->modulePt,
                );
            }
        }

        return $html . '</div>';
    }
}
