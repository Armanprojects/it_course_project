<?php

declare(strict_types=1);

namespace App\Service\Export;

final readonly class ExcelWriter
{
    /** @param array{header: list<string>, rows: list<list<string>>} $table */
    public function write(array $table, string $sheetTitle): string
    {
        $columnCount = \count($table['header']);

        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <?mso-application progid="Excel.Sheet"?>
            <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                      xmlns:o="urn:schemas-microsoft-com:office:office"
                      xmlns:x="urn:schemas-microsoft-com:office:excel"
                      xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
              <Styles>
                <Style ss:ID="head">
                  <Font ss:Bold="1"/>
                  <Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/>
                </Style>
                <Style ss:ID="cell">
                  <Alignment ss:Vertical="Top" ss:WrapText="1"/>
                </Style>
              </Styles>
            XML;

        $xml .= "\n  <Worksheet ss:Name=\"" . $this->sheetName($sheetTitle) . "\">\n";
        $xml .= '    <Table>' . "\n";
        $xml .= str_repeat('      <Column ss:Width="140"/>' . "\n", $columnCount);

        $xml .= '      <Row>' . "\n";

        foreach ($table['header'] as $label) {
            $xml .= '        <Cell ss:StyleID="head"><Data ss:Type="String">'
                . $this->escape($label) . '</Data></Cell>' . "\n";
        }

        $xml .= '      </Row>' . "\n";

        foreach ($table['rows'] as $row) {
            $xml .= '      <Row>' . "\n";

            foreach ($row as $cell) {
                $xml .= '        <Cell ss:StyleID="cell">' . $this->data($cell) . '</Cell>' . "\n";
            }

            $xml .= '      </Row>' . "\n";
        }

        $xml .= '    </Table>' . "\n";

        $xml .= <<<'XML'
                <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
                  <FreezePanes/>
                  <FrozenNoSplit/>
                  <SplitHorizontal>1</SplitHorizontal>
                  <TopRowBottomPane>1</TopRowBottomPane>
                  <ActivePane>2</ActivePane>
                </WorksheetOptions>
              </Worksheet>
            </Workbook>
            XML;

        return $xml;
    }

    private function data(string $value): string
    {
        if ('' !== $value && 1 === preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '<Data ss:Type="Number">' . $value . '</Data>';
        }

        return '<Data ss:Type="String">' . $this->escape($value) . '</Data>';
    }

    private function sheetName(string $title): string
    {
        $name = str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $title);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ('' === $name) {
            $name = 'Резюме';
        }

        return $this->escape(mb_substr($name, 0, 31));
    }

    private function escape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($clean, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}
