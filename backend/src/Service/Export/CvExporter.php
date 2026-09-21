<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\Cv;
use App\Entity\Position;
use App\Repository\CvRepository;

/**
 * Builds the summary table of the CVs submitted to one position.
 *
 * One row per candidate, one column per attribute of the position's template —
 * the shape a recruiter wants in a spreadsheet, where CVs can be sorted and
 * filtered side by side instead of opened one at a time.
 */
final readonly class CvExporter
{
    public function __construct(
        private CvRepository $cvs,
        private ValueFormatter $formatter,
        private CandidateNaming $naming,
    ) {
    }

    /**
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public function tableFor(Position $position, bool $includeDrafts = false): array
    {
        // The template decides the columns, so a candidate who happens to hold
        // extra attributes on their profile does not widen the table.
        $columns = [];

        foreach ($position->getAttributes() as $link) {
            $attribute = $link->getAttribute();
            $columns[] = ['id' => (int) $attribute->getId(), 'name' => $attribute->getName()];
        }

        $rows = [];

        foreach ($this->cvs->findForPosition($position, !$includeDrafts) as $cv) {
            $rows[] = $this->row($cv, $columns);
        }

        return [
            'header' => array_merge(
                ['Кандидат', 'Email', 'Статус', 'Лайки', 'Обновлено'],
                array_column($columns, 'name'),
            ),
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array{id: int, name: string}> $columns
     *
     * @return list<string>
     */
    private function row(Cv $cv, array $columns): array
    {
        // Indexed once per CV: getValueFor() scans the collection, which would
        // turn a wide template into a quadratic walk over every row.
        $values = [];

        foreach ($cv->getProfile()->getAttributeValues() as $value) {
            $values[(int) $value->getAttribute()->getId()] = $value;
        }

        $row = [
            $this->naming->forCv($cv),
            $cv->getCandidate()->getEmail(),
            $cv->isPublished() ? 'опубликовано' : 'черновик',
            (string) $cv->getLikesCount(),
            $cv->getUpdatedAt()->format('d.m.Y H:i'),
        ];

        foreach ($columns as $column) {
            $row[] = $this->formatter->format($values[$column['id']] ?? null);
        }

        return $row;
    }
}
