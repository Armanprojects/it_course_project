<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\Cv;
use App\Entity\PositionAttribute;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Renders a CV as a printable PDF carrying a QR code back to the application.
 *
 * The document is built from the same template the position defines, in the
 * same order the screen shows it, so a printed copy and the page agree.
 */
final readonly class CvPdfGenerator
{
    private const SECTION_TITLES = [
        'personal_information' => 'Личные данные',
        'certification'        => 'Сертификаты',
        'domain_knowledge'     => 'Профессиональные знания',
        'soft_skills'          => 'Гибкие навыки',
    ];

    public function __construct(
        private Environment $twig,
        private ValueFormatter $formatter,
        private CandidateNaming $naming,
        private QrCodeRenderer $qr,
        private string $frontendUrl,
    ) {
    }

    public function generate(Cv $cv): string
    {
        $link = rtrim($this->frontendUrl, '/') . '/cvs/' . $cv->getId();

        $html = $this->twig->render('export/cv.html.twig', [
            'appName'     => 'CVMatch',
            'title'       => $this->naming->forCv($cv) . ' — ' . $cv->getPosition()->getTitle(),
            'candidate'   => $this->naming->forCv($cv),
            'email'       => $cv->getCandidate()->getEmail(),
            'position'    => [
                'title'   => $cv->getPosition()->getTitle(),
                'company' => $cv->getPosition()->getCompany(),
                'level'   => $cv->getPosition()->getLevel(),
            ],
            'published'   => $cv->getPublishedAt()?->format('d.m.Y'),
            'generatedAt' => (new \DateTimeImmutable())->format('d.m.Y'),
            'link'        => $link,
            'qr'          => $this->qr->toHtml($link),
            'sections'    => $this->sections($cv),
            'projects'    => $this->projects($cv),
        ]);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        // The document is built entirely from our own markup, so nothing should
        // be fetched over the network while rendering it: a slow or dead remote
        // host would otherwise hold the request open.
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function fileName(Cv $cv): string
    {
        return \sprintf(
            '%s-%s.pdf',
            $this->naming->toFileName($this->naming->forCv($cv)),
            $this->naming->toFileName($cv->getPosition()->getTitle()),
        );
    }

    /**
     * Mirrors CvSerializer: personal information opens the document, the rest
     * keeps the order the position gave its attributes.
     *
     * @return list<array{title: string, attributes: list<array{name: string, value: string, empty: bool, isUrl: bool}>}>
     */
    private function sections(Cv $cv): array
    {
        $profile  = $cv->getProfile();
        $grouped  = [];

        foreach ($cv->getPosition()->getAttributes() as $link) {
            $attribute = $link->getAttribute();
            $value     = $profile->getValueFor($attribute);
            $section   = $link->getSection() ?? $attribute->getCategory()->value;

            $grouped[$section][] = [
                'name'  => $attribute->getName(),
                'value' => $this->formatter->format($value),
                'empty' => null === $value || $value->isEmpty(),
                'isUrl' => $this->isUrl($link, $value?->isEmpty() ?? true),
            ];
        }

        $personal = AttributeCategory::PersonalInformation->value;
        $ordered  = [];

        if (isset($grouped[$personal])) {
            $ordered[] = ['title' => $this->sectionTitle($personal), 'attributes' => $grouped[$personal]];
            unset($grouped[$personal]);
        }

        foreach ($grouped as $name => $rows) {
            $ordered[] = ['title' => $this->sectionTitle((string) $name), 'attributes' => $rows];
        }

        return $ordered;
    }

    /**
     * @return list<array{name: string, period: string, description: ?string, tags: list<string>}>
     */
    private function projects(Cv $cv): array
    {
        $projects = [];

        foreach ($cv->getRelevantProjects() as $project) {
            $projects[] = [
                'name'        => $project->getName(),
                'period'      => $this->period($project->getPeriodFrom(), $project->getPeriodTo(), $project->isOngoing()),
                // Markdown is printed as written: rendering it would mean
                // pulling a parser in just for the few projects that use it.
                'description' => $project->getDescription(),
                'tags'        => array_map(
                    static fn ($tag): string => $tag->getName(),
                    $project->getTags()->toArray(),
                ),
            ];
        }

        return $projects;
    }

    private function period(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $ongoing): string
    {
        $start = $from?->format('m.Y');
        $end   = $ongoing ? 'по настоящее время' : $to?->format('m.Y');

        return match (true) {
            null !== $start && null !== $end => $start . ' — ' . $end,
            null !== $start                  => $start,
            null !== $end                    => $end,
            default                          => '',
        };
    }

    private function isUrl(PositionAttribute $link, bool $empty): bool
    {
        return !$empty && AttributeType::Image === $link->getAttribute()->getType();
    }

    /**
     * A recruiter may name a section freely in the template; only the built-in
     * category codes get a translated title.
     */
    private function sectionTitle(string $section): string
    {
        return self::SECTION_TITLES[$section] ?? $section;
    }
}
