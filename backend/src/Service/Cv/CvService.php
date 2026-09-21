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

final readonly class CvService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AccessRuleEvaluator $access,
        private AttributeValueWriter $writer,
    ) {
    }

    public function start(Profile $profile, Position $position): Cv
    {
        if (!$this->access->allows($position, $profile)) {
            throw new AccessDeniedHttpException('Эта позиция вам недоступна.');
        }

        $existing = $profile->getCvFor($position);

        if (null !== $existing) {
            return $existing;
        }

        $cv = $profile->startCv($position);
        $this->em->persist($cv);

        $this->attachTemplateAttributes($profile, $position);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Резюме на эту позицию уже создано.');
        }

        return $cv;
    }

    private function attachTemplateAttributes(Profile $profile, Position $position): void
    {
        foreach ($position->getAttributes() as $link) {
            $profile->addAttribute($link->getAttribute());
        }
    }

    public function editAttribute(Cv $cv, int $attributeId, mixed $value, int $version): Cv
    {
        $profile = $cv->getProfile();

        if ($profile->getVersion() !== $version) {
            throw new ConflictException($profile->getVersion());
        }

        $attribute = $this->templateAttribute($cv, $attributeId);

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

    private function templateAttribute(Cv $cv, int $attributeId): Attribute
    {
        foreach ($cv->getPosition()->getAttributes() as $link) {
            if ($link->getAttribute()->getId() === $attributeId) {
                return $link->getAttribute();
            }
        }

        throw new NotFoundHttpException('Этот атрибут не входит в резюме.');
    }

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

    public function like(Cv $cv, User $recruiter): Cv
    {
        $this->assertRecruiter($recruiter);

        $like = $cv->like($recruiter);

        if (null !== $like) {
            $this->em->persist($like);

            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
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
