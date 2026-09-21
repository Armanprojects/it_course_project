<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\Cv;
use App\Entity\Profile;

final readonly class CandidateNaming
{
    private const FIRST_NAME = 'first name';
    private const LAST_NAME  = 'last name';

    public function forCv(Cv $cv): string
    {
        return $this->forProfile($cv->getProfile());
    }

    public function forProfile(Profile $profile): string
    {
        $parts = [];

        foreach ($profile->getAttributeValues() as $value) {
            $name = mb_strtolower($value->getAttribute()->getName());

            if (\in_array($name, [self::FIRST_NAME, self::LAST_NAME], true) && !$value->isEmpty()) {
                $parts[$name] = (string) $value->getValueString();
            }
        }

        $full = trim(($parts[self::FIRST_NAME] ?? '') . ' ' . ($parts[self::LAST_NAME] ?? ''));

        return '' !== $full ? $full : $profile->getUser()->getEmail();
    }

    public function toFileName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? '';
        $ascii = trim($ascii, '-');

        return '' !== $ascii ? mb_strtolower($ascii) : 'cv';
    }
}
