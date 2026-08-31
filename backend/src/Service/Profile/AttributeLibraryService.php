<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Dto\SaveAttributeRequest;
use App\Entity\Attribute;
use App\Entity\AttributeValue;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Repository\AttributeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Write side of the shared attribute library.
 *
 * Recruiters manage one common pool, so there is no ownership here either —
 * only the role gate at the controller.
 */
final readonly class AttributeLibraryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeRepository $attributes,
    ) {
    }

    public function create(SaveAttributeRequest $request, User $author): Attribute
    {
        $attribute = new Attribute(
            $request->name,
            $request->categoryEnum(),
            $request->typeEnum(),
            $author,
        );

        $attribute->setDescription($request->description);
        $this->applyOptions($attribute, $request);

        $this->em->persist($attribute);
        $this->flush();

        return $attribute;
    }

    /**
     * The type is deliberately not editable: every stored value lives in a
     * column chosen by it, so changing it would orphan all of them.
     */
    public function update(Attribute $attribute, SaveAttributeRequest $request): Attribute
    {
        $this->assertVersion($attribute, $request->version);

        $attribute->setName($request->name);
        $attribute->setDescription($request->description);
        $attribute->setCategory($request->categoryEnum());
        $this->applyOptions($attribute, $request);

        $this->flush($attribute);

        return $attribute;
    }

    /**
     * Soft delete: stored values and position links survive, the attribute just
     * leaves the library and stops being offered.
     *
     * Built-ins refuse outright — the brief says the "Me" section always exists.
     */
    public function remove(Attribute $attribute): void
    {
        try {
            $attribute->remove();
        } catch (\LogicException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $this->em->flush();
    }

    public function restore(Attribute $attribute): void
    {
        $attribute->restore();
        $this->em->flush();
    }

    /**
     * Where an attribute is in use, so the UI can warn before removing it.
     *
     * Three counting queries, never a walk over the collections.
     *
     * @return array{profiles: int, positions: int, rules: int}
     */
    public function usage(Attribute $attribute): array
    {
        return [
            'profiles'  => $this->countBy(AttributeValue::class, $attribute),
            'positions' => $this->countBy(PositionAttribute::class, $attribute),
            'rules'     => $this->countBy(PositionAccessRule::class, $attribute),
        ];
    }

    /**
     * @param class-string $entity
     */
    private function countBy(string $entity, Attribute $attribute): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($entity, 'e')
            ->andWhere('e.attribute = :attribute')
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function applyOptions(Attribute $attribute, SaveAttributeRequest $request): void
    {
        if (!$attribute->getType()->requiresOptions()) {
            return;
        }

        $options = $request->cleanOptions();

        if ([] === $options) {
            throw new BadRequestHttpException('Для типа «выбор из списка» нужен хотя бы один вариант.');
        }

        // Dropping an option that profiles already store would leave those
        // values pointing at a choice that no longer exists.
        $removed = array_diff($attribute->getOptions(), $options);

        if ([] !== $removed && $this->optionsInUse($attribute, $removed)) {
            throw new BadRequestHttpException(sprintf(
                'Варианты «%s» уже выбраны в профилях — их нельзя удалить.',
                implode('», «', $removed),
            ));
        }

        $attribute->setOptions($options);
    }

    /**
     * @param list<string> $options
     */
    private function optionsInUse(Attribute $attribute, array $options): bool
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(AttributeValue::class, 'v')
            ->andWhere('v.attribute = :attribute')
            ->andWhere('v.valueOption IN (:options)')
            ->setParameter('attribute', $attribute)
            ->setParameter('options', array_values($options))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function assertVersion(Attribute $attribute, ?int $version): void
    {
        if (null !== $version && $attribute->getVersion() !== $version) {
            throw new ConflictException($attribute->getVersion());
        }
    }

    private function flush(?Attribute $attribute = null): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Names are globally unique, case-insensitively; the index is what
            // actually decides when two recruiters add the same one at once.
            throw new ConflictHttpException('Атрибут с таким названием уже существует.');
        } catch (OptimisticLockException) {
            if (null !== $attribute) {
                $this->em->refresh($attribute);

                throw new ConflictException($attribute->getVersion());
            }

            throw new ConflictHttpException('Атрибут изменился, перезагрузите страницу.');
        }
    }
}
