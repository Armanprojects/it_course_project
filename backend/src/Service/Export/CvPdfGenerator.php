<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\Cv;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

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
        private PhotoFetcher $photos,
        private string $frontendUrl,
    ) {
    }

    public function generate(Cv $cv): string
    {
        $link = rtrim($this->frontendUrl, '/') . '/cvs/' . $cv->getId();
        // Фото тянется по сети, поэтому берём его один раз: и в шапку, и как
        // признак того, что строку с адресом из таблицы можно убрать.
        $photo = $this->photo($cv);

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
            'photo'       => $photo,
            'sections'    => $this->sections($cv, null !== $photo),
            'projects'    => $this->projects($cv),
        ]);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');

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
     * Фото кандидата для шапки — первое заполненное изображение шаблона.
     *
     * Картинка одна на документ: шаблон может просить несколько изображений,
     * но портрет в шапке ровно один, остальные остаются строками таблицы.
     */
    private function photo(Cv $cv): ?string
    {
        $profile = $cv->getProfile();

        foreach ($cv->getPosition()->getAttributes() as $link) {
            if (AttributeType::Image !== $link->getAttribute()->getType()) {
                continue;
            }

            $value = $profile->getValueFor($link->getAttribute());

            if (null === $value || $value->isEmpty()) {
                continue;
            }

            $photo = $this->photos->toDataUri($value->getValueImageUrl());

            if (null !== $photo) {
                return $photo;
            }
        }

        return null;
    }

    /** @return list<array{title: string, attributes: list<array{name: string, value: string, empty: bool}>}> */
    private function sections(Cv $cv, bool $photoUsed): array
    {
        $profile = $cv->getProfile();
        $grouped = [];

        foreach ($cv->getPosition()->getAttributes() as $link) {
            $attribute = $link->getAttribute();
            $value     = $profile->getValueFor($attribute);
            $section   = $link->getSection() ?? $attribute->getCategory()->value;

            // Фото ушло в шапку — строкой с адресом его дублировать незачем.
            if ($photoUsed && AttributeType::Image === $attribute->getType()) {
                continue;
            }

            $grouped[$section][] = [
                'name'  => $attribute->getName(),
                'value' => $this->formatter->format($value),
                'empty' => null === $value || $value->isEmpty(),
            ];
        }

        $personal = AttributeCategory::PersonalInformation->value;
        $ordered  = [];

        // Секция могла состоять из одного фото — тогда она опустела.
        $grouped = array_filter($grouped, static fn (array $rows): bool => [] !== $rows);

        if (isset($grouped[$personal])) {
            $ordered[] = ['title' => $this->sectionTitle($personal), 'attributes' => $grouped[$personal]];
            unset($grouped[$personal]);
        }

        foreach ($grouped as $name => $rows) {
            $ordered[] = ['title' => $this->sectionTitle((string) $name), 'attributes' => $rows];
        }

        return $ordered;
    }

    /** @return list<array{name: string, period: string, description: ?string, tags: list<string>}> */
    private function projects(Cv $cv): array
    {
        $projects = [];

        foreach ($cv->getRelevantProjects() as $project) {
            $projects[] = [
                'name'        => $project->getName(),
                'period'      => $this->period($project->getPeriodFrom(), $project->getPeriodTo(), $project->isOngoing()),
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

    private function sectionTitle(string $section): string
    {
        return self::SECTION_TITLES[$section] ?? $section;
    }
}
