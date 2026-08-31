<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\Profile;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Decides whether a candidate may create a CV for a position.
 *
 * A position is either public — open to every authenticated user — or gated by
 * a set of rules that are ANDed together, each testing one attribute of the
 * candidate's profile.
 *
 * The check runs in SQL as a single EXISTS-per-rule query rather than in PHP:
 * the candidate list of a position and the position list of a candidate both
 * need it in bulk, and evaluating it row by row would be a query in a loop.
 */
final readonly class AccessRuleEvaluator
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Does this profile satisfy every rule of the position?
     */
    public function allows(Position $position, Profile $profile): bool
    {
        if ($position->isPublic()) {
            return true;
        }

        $rules = $position->getAccessRules();

        if (0 === $rules->count()) {
            // Restricted with no rules is a position nobody qualifies for by
            // filter — treat it as closed rather than silently open, so a
            // half-configured position never leaks.
            return false;
        }

        foreach ($rules as $rule) {
            if (!$this->matches($rule, $profile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The ids of every position the profile may build a CV for.
     *
     * One query for the public ones plus one per rule, regardless of how many
     * positions exist — the alternative, walking positions and asking per row,
     * is exactly the loop the brief forbids.
     *
     * @return list<int>
     */
    public function allowedPositionIds(Profile $profile): array
    {
        /** @var list<array{id: int, public: bool}> $positions */
        $positions = $this->em->createQueryBuilder()
            ->select('p.id', 'p.public')
            ->from(Position::class, 'p')
            ->getQuery()
            ->getArrayResult();

        // Every rule in one query, with its attribute joined in: the matcher
        // reads the attribute's type, and fetching that lazily would mean a
        // query per rule.
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

    /**
     * Evaluates one rule against the profile's stored value.
     *
     * "Is set" is about presence, everything else compares — and a missing
     * value can never satisfy a comparison, so it short-circuits to false.
     */
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
            // Period and image support only "is set", handled above.
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
