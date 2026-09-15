<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\Entity\Attribute;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\ConflictException;
use App\Service\Position\AccessRuleEvaluator;
use App\Service\Profile\AttributeValueWriter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private AttributeValueWriter $writer,
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

        // The template's attributes are attached to the profile right away, so
        // the candidate can fill a field straight from the CV. Without this the
        // value has nowhere to be written and the attribute would only be
        // reachable by hunting it down in the library.
        $this->attachTemplateAttributes($profile, $position);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Резюме на эту позицию уже создано.');
        }

        return $cv;
    }

    /**
     * Attaches every attribute of the position's template to the profile,
     * keeping the values already filled in — addAttribute() is a no-op for an
     * attribute the profile already carries, so nothing is overwritten.
     */
    private function attachTemplateAttributes(Profile $profile, Position $position): void
    {
        foreach ($position->getAttributes() as $link) {
            $profile->addAttribute($link->getAttribute());
        }
    }

    /**
     * In-place editing of one attribute from the CV page.
     *
     * The value is written to the candidate's profile, not to the CV: the brief
     * keeps a single master value per attribute, so editing it here is exactly
     * the same write the profile page performs, and the change shows up in
     * every other CV of that candidate.
     *
     * Goes through the profile's version, sharing the optimistic-locking gate
     * with autosave — a CV tab and a profile tab editing the same value cannot
     * silently overwrite one another.
     */
    public function editAttribute(Cv $cv, int $attributeId, mixed $value, int $version): Cv
    {
        $profile = $cv->getProfile();

        if ($profile->getVersion() !== $version) {
            throw new ConflictException($profile->getVersion());
        }

        $attribute = $this->templateAttribute($cv, $attributeId);

        // addAttribute() returns the existing value when there is one, so a
        // field the candidate never filled is created on first edit.
        $this->writer->write($profile->addAttribute($attribute), $value);
        $profile->touch();
        $cv->touch();

        try {
            $this->em->flush();
        } catch (OptimisticLockException) {
            $this->em->refresh($profile);

            throw new ConflictException($profile->getVersion());
        }

        return $cv;
    }

    /**
     * Only attributes the position actually asks for may be written from a CV:
     * the page must not become a way to edit arbitrary parts of a profile.
     */
    private function templateAttribute(Cv $cv, int $attributeId): Attribute
    {
        foreach ($cv->getPosition()->getAttributes() as $link) {
            if ($link->getAttribute()->getId() === $attributeId) {
                return $link->getAttribute();
            }
        }

        throw new NotFoundHttpException('Этот атрибут не входит в резюме.');
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
