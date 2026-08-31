<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\Dto\SavePositionRequest;
use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use App\Exception\ConflictException;
use App\Repository\AttributeRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Write side of the position catalogue.
 *
 * There is deliberately no ownership check anywhere here: the brief is explicit
 * that all recruiters share one set of positions and any of them may edit any
 * position. The only gate is the role, enforced at the controller.
 */
final readonly class PositionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeRepository $attributes,
        private TagRepository $tags,
    ) {
    }

    public function create(SavePositionRequest $request, User $author): Position
    {
        $position = new Position($request->title, $author);

        $this->apply($position, $request);
        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    public function update(Position $position, SavePositionRequest $request): Position
    {
        $this->assertVersion($position, $request->version);

        $position->setTitle($request->title);
        $this->apply($position, $request);

        return $this->flush($position);
    }

    /**
     * Copies a position with its whole template — attributes, rules and tags.
     *
     * The copy starts as a draft that is not public: duplicating a restricted
     * position and silently publishing the copy would widen access by accident.
     */
    public function duplicate(Position $source, User $author): Position
    {
        $copy = new Position($this->copyTitle($source->getTitle()), $author);
        $copy->setShortDescription($source->getShortDescription());
        $copy->setCompany($source->getCompany());
        $copy->setLevel($source->getLevel());
        $copy->setMaxProjects($source->getMaxProjects());
        $copy->setPublic(false);

        foreach ($source->getAttributes() as $link) {
            $copied = $copy->addAttribute($link->getAttribute(), $link->getSortOrder());
            $copied->setRequired($link->isRequired());
            $copied->setSection($link->getSection());
        }

        foreach ($source->getAccessRules() as $rule) {
            $this->copyRule($copy, $rule);
        }

        foreach ($source->getProjectTags() as $tag) {
            $copy->addProjectTag($tag);
        }

        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }

    /**
     * Deleting a position takes its CVs and discussion with it — both are
     * meaningless without the template they were built from, and the schema
     * cascades accordingly.
     */
    public function delete(Position $position): void
    {
        foreach ($position->getProjectTags() as $tag) {
            $position->removeProjectTag($tag);
        }

        $this->em->remove($position);
        $this->em->flush();
    }

    private function apply(Position $position, SavePositionRequest $request): void
    {
        $position->setShortDescription($request->shortDescription);
        $position->setCompany($request->company);
        $position->setLevel($request->level);
        $position->setPublic($request->public);

        try {
            $position->setMaxProjects($request->maxProjects);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $this->syncAttributes($position, $request->normalizedAttributes());
        $this->syncRules($position, $request->normalizedRules());
        $this->syncTags($position, $request->cleanTags());

        $position->touch();
    }

    /**
     * Brings the template in line with the submitted list.
     *
     * Dropped attributes are soft-removed rather than deleted: CVs already
     * generated from this position keep rendering them, they just stop being
     * offered for new ones.
     *
     * @param list<array{attributeId: int, required: bool, section: ?string, sortOrder: int}> $rows
     */
    private function syncAttributes(Position $position, array $rows): void
    {
        $wanted     = [];
        $attributes = $this->loadAttributes(array_column($rows, 'attributeId'));

        foreach ($rows as $row) {
            $attribute = $attributes[$row['attributeId']] ?? null;

            if (null === $attribute) {
                throw new BadRequestHttpException(
                    sprintf('Атрибут #%d не найден.', $row['attributeId']),
                );
            }

            $link = $position->addAttribute($attribute, $row['sortOrder']);
            $link->setRequired($row['required']);
            $link->setSection($row['section']);
            $link->setSortOrder($row['sortOrder']);

            $wanted[$attribute->getId()] = true;
        }

        foreach ($position->getAttributes() as $link) {
            if (!isset($wanted[$link->getAttribute()->getId()])) {
                $link->remove();
            }
        }
    }

    /**
     * Rules are replaced wholesale: they are a small, unordered set, and
     * matching submitted rows to existing rows would need an identity the
     * client does not have.
     *
     * @param list<array{attributeId: int, operator: string, value: mixed}> $rows
     */
    private function syncRules(Position $position, array $rows): void
    {
        foreach ($position->getAccessRules()->toArray() as $existing) {
            $position->removeAccessRule($existing);
            $this->em->remove($existing);
        }

        if ([] === $rows) {
            return;
        }

        $attributes = $this->loadAttributes(array_column($rows, 'attributeId'));

        foreach ($rows as $row) {
            $attribute = $attributes[$row['attributeId']] ?? null;

            if (null === $attribute) {
                throw new BadRequestHttpException(
                    sprintf('Атрибут #%d не найден.', $row['attributeId']),
                );
            }

            $operator = FilterOperator::tryFrom($row['operator']);

            if (null === $operator) {
                throw new BadRequestHttpException(
                    sprintf('Неизвестный оператор "%s".', $row['operator']),
                );
            }

            $this->buildRule($position, $attribute, $operator, $row['value']);
        }
    }

    private function buildRule(
        Position $position,
        Attribute $attribute,
        FilterOperator $operator,
        mixed $value,
    ): void {
        try {
            // The constructor rejects an operator the attribute type does not
            // support — "contains" on a checkbox, say.
            $rule = new PositionAccessRule($position, $attribute, $operator);

            if (FilterOperator::IsSet !== $operator) {
                $this->applyOperand($rule, $attribute->getType(), $operator, $value);
            }

            $this->em->persist($rule);
        } catch (\InvalidArgumentException|\LogicException $e) {
            throw new BadRequestHttpException(sprintf(
                'Правило по атрибуту «%s»: %s',
                $attribute->getName(),
                $e->getMessage(),
            ));
        }
    }

    private function applyOperand(
        PositionAccessRule $rule,
        AttributeType $type,
        FilterOperator $operator,
        mixed $value,
    ): void {
        match ($type) {
            AttributeType::Numeric => $rule->setOperandNumber(
                is_numeric($value)
                    ? $value
                    : throw new \InvalidArgumentException('ожидается число.'),
            ),
            AttributeType::Boolean => $rule->setOperandBool(
                filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? false,
            ),
            AttributeType::Date => $rule->setOperandDate($this->toDate($value)),
            AttributeType::Select => FilterOperator::In === $operator
                ? $rule->setOperandOptions($this->toStringList($value))
                : $rule->setOperandString($this->toStringValue($value)),
            AttributeType::String, AttributeType::Text => $rule->setOperandString(
                $this->toStringValue($value),
            ),
            default => throw new \InvalidArgumentException(
                'для этого типа доступна только проверка «заполнено».',
            ),
        };
    }

    private function toDate(mixed $value): \DateTimeImmutable
    {
        $date = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        if (false === $date) {
            throw new \InvalidArgumentException('ожидается дата в формате ГГГГ-ММ-ДД.');
        }

        return $date;
    }

    private function toStringValue(mixed $value): string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            throw new \InvalidArgumentException('ожидается строка.');
        }

        return trim((string) $value);
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('ожидается список значений.');
        }

        return array_values(array_map($this->toStringValue(...), $value));
    }

    /**
     * @param list<string> $names
     */
    private function syncTags(Position $position, array $names): void
    {
        $wanted = [];

        foreach ($names as $name) {
            $tag = $this->tags->findOneByName($name);

            if (null === $tag) {
                $tag = new Tag($name);
                $this->em->persist($tag);
            }

            $wanted[$tag->getNameNormalized()] = $tag;
        }

        foreach ($position->getProjectTags()->toArray() as $tag) {
            if (!isset($wanted[$tag->getNameNormalized()])) {
                $position->removeProjectTag($tag);
            }
        }

        foreach ($wanted as $tag) {
            $position->addProjectTag($tag);
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Attribute>
     */
    private function loadAttributes(array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ([] === $ids) {
            return [];
        }

        /** @var list<Attribute> $found */
        $found = $this->attributes->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];

        foreach ($found as $attribute) {
            $byId[$attribute->getId()] = $attribute;
        }

        return $byId;
    }

    private function copyRule(Position $copy, PositionAccessRule $source): void
    {
        $rule = new PositionAccessRule($copy, $source->getAttribute(), $source->getOperator());

        match ($source->getType()) {
            AttributeType::Numeric => $rule->setOperandNumber($source->getOperandNumber()),
            AttributeType::Boolean => $rule->setOperandBool($source->getOperandBool()),
            AttributeType::Date    => $rule->setOperandDate($source->getOperandDate()),
            AttributeType::Select  => [] !== $source->getOperandOptions()
                ? $rule->setOperandOptions($source->getOperandOptions())
                : $rule->setOperandString($source->getOperandString()),
            AttributeType::String, AttributeType::Text => $rule->setOperandString(
                $source->getOperandString(),
            ),
            default => null,
        };

        $this->em->persist($rule);
    }

    private function copyTitle(string $title): string
    {
        return mb_substr($title . ' (копия)', 0, 180);
    }

    private function assertVersion(Position $position, ?int $version): void
    {
        if (null !== $version && $position->getVersion() !== $version) {
            throw new ConflictException($position->getVersion());
        }
    }

    private function flush(Position $position): Position
    {
        try {
            $this->em->flush();
        } catch (OptimisticLockException) {
            $this->em->refresh($position);

            throw new ConflictException($position->getVersion());
        }

        return $position;
    }
}
