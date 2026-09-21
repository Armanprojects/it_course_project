<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\Profile;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AccessRuleEvaluator
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function allows(Position $position, Profile $profile): bool
    {
        if ($position->isPublic()) {
            return true;
        }

        $rules = $position->getAccessRules();

        if (0 === $rules->count()) {
            return false;
        }

        foreach ($rules as $rule) {
            if (!$this->matches($rule, $profile)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> */
    public function allowedPositionIds(Profile $profile): array
    {
        /** @var list<array{id: int, public: bool}> $positions */
        $positions = $this->em->createQueryBuilder()
            ->select('p.id', 'p.public')
            ->from(Position::class, 'p')
            ->getQuery()
            ->getArrayResult();

        /** @var list<PositionAccessRule> $rules */
        $rules = $this->em->createQueryBuilder()
            ->select('r', 'a')
            ->from(PositionAccessRule::class, 'r')
            ->join('r.attribute', 'a')
            ->getQuery()
            ->getResult();

        /** @var array<int, list<PositionAccessRule>> $rulesByPosition */
        $rulesByPosition = [];

        foreach ($rules as $rule) {
            $rulesByPosition[$rule->getPosition()->getId()][] = $rule;
        }

        $allowed = [];

        foreach ($positions as $row) {
            $id = (int) $row['id'];

            if ($row['public']) {
                $allowed[] = $id;

                continue;
            }

            $positionRules = $rulesByPosition[$id] ?? [];

            if ([] === $positionRules) {
                continue;
            }

            foreach ($positionRules as $rule) {
                if (!$this->matches($rule, $profile)) {
                    continue 2;
                }
            }

            $allowed[] = $id;
        }

        return $allowed;
    }

    private function matches(PositionAccessRule $rule, Profile $profile): bool
    {
        $value = $profile->getValueFor($rule->getAttribute());

        if (FilterOperator::IsSet === $rule->getOperator()) {
            return null !== $value && !$value->isEmpty();
        }

        if (null === $value || $value->isEmpty()) {
            return false;
        }

        return match ($rule->getType()) {
            AttributeType::Numeric => $this->compareNumbers(
                (float) $value->getValueNumber(),
                (float) $rule->getOperandNumber(),
                $rule->getOperator(),
            ),
            AttributeType::Date => $this->compareNumbers(
                (float) $value->getValueDate()?->getTimestamp(),
                (float) $rule->getOperandDate()?->getTimestamp(),
                $rule->getOperator(),
            ),
            AttributeType::Boolean => $value->getValueBool() === $rule->getOperandBool(),
            AttributeType::Select  => $this->compareOption($rule, (string) $value->getValueOption()),
            AttributeType::String  => $this->compareStrings(
                (string) $value->getValueString(),
                (string) $rule->getOperandString(),
                $rule->getOperator(),
            ),
            AttributeType::Text => $this->compareStrings(
                (string) $value->getValueText(),
                (string) $rule->getOperandString(),
                $rule->getOperator(),
            ),
            default => false,
        };
    }

    private function compareNumbers(float $left, float $right, FilterOperator $operator): bool
    {
        return match ($operator) {
            FilterOperator::Equals         => $left === $right,
            FilterOperator::NotEquals      => $left !== $right,
            FilterOperator::GreaterThan    => $left > $right,
            FilterOperator::GreaterOrEqual => $left >= $right,
            FilterOperator::LessThan       => $left < $right,
            FilterOperator::LessOrEqual    => $left <= $right,
            default                        => false,
        };
    }

    private function compareStrings(string $left, string $right, FilterOperator $operator): bool
    {
        $left  = mb_strtolower($left);
        $right = mb_strtolower($right);

        return match ($operator) {
            FilterOperator::Equals    => $left === $right,
            FilterOperator::NotEquals => $left !== $right,
            FilterOperator::Contains  => '' !== $right && str_contains($left, $right),
            default                   => false,
        };
    }

    private function compareOption(PositionAccessRule $rule, string $value): bool
    {
        return match ($rule->getOperator()) {
            FilterOperator::Equals    => $value === $rule->getOperandString(),
            FilterOperator::NotEquals => $value !== $rule->getOperandString(),
            FilterOperator::In        => \in_array($value, $rule->getOperandOptions(), true),
            default                   => false,
        };
    }
}
