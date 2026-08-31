<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Position\AccessRuleEvaluator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Creating, publishing and liking CVs.
 *
 * A CV holds no attribute values of its own: it is the candidate's profile
 * rendered through a position's template, so "editing a CV" is editing the
 * profile behind it.
 */
final readonly class CvService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccessRuleEvaluator $access,
    ) {
    }

    /**
     * Starts a CV for a position, re-checking access on the server: the client
     * decides what to show, never what is allowed.
     */
    public function start(Profile $profile, Position $position): Cv
    {
        if (!$this->access->allows($position, $profile)) {
            throw new AccessDeniedHttpException('Эта позиция вам недоступна.');
        }

        $existing = $profile->getCvFor($position);

        if (null !== $existing) {
            // At most one CV per candidate per position — returning the
            // existing one is friendlier than an error the UI must decode.
            return $existing;
        }

        $cv = $profile->startCv($position);
        $this->em->persist($cv);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Резюме на эту позицию уже создано.');
        }

        return $cv;
    }

    /**
     * Publishing is what makes a CV visible to recruiters, so it is refused
     * until every attribute of the template carries a value.
     */
    public function publish(Cv $cv): Cv
    {
        try {
            $cv->publish();
        } catch (\LogicException) {
            $missing = array_map(
                static fn ($attribute): string => $attribute->getName(),
                $cv->getMissingAttributes(),
            );

            throw new BadRequestHttpException(sprintf(
                'Заполните все поля резюме: %s.',
                implode(', ', $missing),
            ));
        }

        $this->em->flush();

        return $cv;
    }

    public function unpublish(Cv $cv): Cv
    {
        $cv->unpublish();
        $this->em->flush();

        return $cv;
    }

    public function delete(Cv $cv): void
    {
        $this->em->remove($cv);
        $this->em->flush();
    }

    /**
     * Only recruiters may like, at most once each — both rules live in the
     * entity, this just enforces the role and persists.
     */
    public function like(Cv $cv, User $recruiter): Cv
    {
        $this->assertRecruiter($recruiter);

        $like = $cv->like($recruiter);

        if (null !== $like) {
            $this->em->persist($like);

            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                // Two clicks racing: the unique index decides, the counter
                // already reflects one like.
                $this->em->refresh($cv);
            }
        }

        return $cv;
    }

    public function unlike(Cv $cv, User $recruiter): Cv
    {
        $this->assertRecruiter($recruiter);

        if ($cv->unlike($recruiter)) {
            $this->em->flush();
        }

        return $cv;
    }

    private function assertRecruiter(User $user): void
    {
        if (!$user->hasRole(UserRole::Recruiter) && !$user->hasRole(UserRole::Admin)) {
            throw new AccessDeniedHttpException('Ставить лайки могут только рекрутеры.');
        }
    }
}
