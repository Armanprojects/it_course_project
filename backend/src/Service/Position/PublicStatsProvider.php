<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\Enum\UserRole;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Repository\UserRepository;

/**
 * The figures the home page shows to everyone, signed in or not.
 *
 * Only aggregates leave this class: counting CVs is public information,
 * the CVs themselves are not.
 */
final readonly class PublicStatsProvider
{
    private const RECENT_WINDOW = '-24 hours';

    public function __construct(
        private PositionRepository $positions,
        private CvRepository $cvs,
        private UserRepository $users,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function collect(): array
    {
        return [
            'positions'      => $this->positions->countAll(),
            'cvs'            => $this->cvs->countAll(),
            'submittedCvs'   => $this->cvs->countPublished(),
            'cvsLast24h'     => $this->cvs->countCreatedSince(new \DateTimeImmutable(self::RECENT_WINDOW)),
            'candidates'     => $this->users->countByRole(UserRole::Candidate),
            'recruiters'     => $this->users->countByRole(UserRole::Recruiter),
        ];
    }
}
