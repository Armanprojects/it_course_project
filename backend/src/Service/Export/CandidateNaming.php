<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\Cv;
use App\Entity\Profile;

/**
 * The candidate's display name, built from the built-in name attributes.
 *
 * Shared by the CV tables, the spreadsheet export and the printed document, so
 * that one person is called the same thing everywhere — and so that a file name
 * can be derived from it without duplicating the fallback rules.
 */
final readonly class CandidateNaming
{
    private const FIRST_NAME = 'first name';
    private const LAST_NAME  = 'last name';

    public function forCv(Cv $cv): string
    {
        return $this->forProfile($cv->getProfile());
    }

    /**
     * Falls back to the address: a row with no name at all would be unusable in
     * a recruiter's table.
     */
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

    /**
     * A name safe to put in a Content-Disposition header: transliteration is
     * deliberately skipped, since the header carries the real name UTF-8
     * encoded and this is only the ASCII fallback for older clients.
     */
    public function toFileName(string $name): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? '';
        $ascii = trim($ascii, '-');

        return '' !== $ascii ? mb_strtolower($ascii) : 'cv';
    }
}
