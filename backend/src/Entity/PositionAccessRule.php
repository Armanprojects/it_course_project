<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use Doctrine\ORM\Mapping as ORM;


/**
 * A single condition restricting access to a position, e.g.
 * "IELTS Score" > 7.0, "Remote Work" is checked, "Presentation Skills" = "Advanced".
 * All rules of a position are combined with AND.
 */
#[ORM\Entity]
#[ORM\Table(name: 'position_access_rule')]
#[ORM\Index(name: 'idx_access_rule_attribute', columns: ['attribute_id'])]
class PositionAccessRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    /**
     * No onDelete: the database must refuse to drop an attribute still in use.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Attribute $attribute;

    #[ORM\Column(length: 16, enumType: FilterOperator::class)]
    private FilterOperator $operator;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $operandString = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $operandNumber = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $operandDate = null;

    #[ORM\Column(nullable: true)]
    private ?bool $operandBool = null;

    /**
     * Choices for FilterOperator::In.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $operandOptions = null;

    public function __construct(Position $position, Attribute $attribute, FilterOperator $operator)
    {
        $this->position  = $position;
        $this->attribute = $attribute;

        $this->setOperator($operator);

        $position->addAccessRule($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function setPosition(Position $position): void
    {
        $this->position = $position;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getType(): AttributeType
    {
        return $this->attribute->getType();
    }

    public function getOperator(): FilterOperator
    {
        return $this->operator;
    }

    public function setOperator(FilterOperator $operator): void
    {
        if (!$this->getType()->supports($operator)) {
            throw new \InvalidArgumentException(sprintf(
                'Operator "%s" is not supported by attribute type "%s".',
                $operator->value,
                $this->getType()->value,
            ));
        }

        $this->operator = $operator;
    }

    public function getOperandString(): ?string
    {
        return $this->operandString;
    }

    public function setOperandString(?string $operand): void
    {
        $this->assertType(AttributeType::String, AttributeType::Text, AttributeType::Select);

        if (
            null !== $operand
            && AttributeType::Select === $this->getType()
            && !$this->attribute->hasOption($operand)
        ) {
            throw new \InvalidArgumentException(sprintf('Option "%s" is not defined for this attribute.', $operand));
        }

        $this->operandString = $operand;
    }

    public function getOperandNumber(): ?string
    {
        return $this->operandNumber;
    }

    public function setOperandNumber(string|int|float|null $operand): void
    {
        $this->assertType(AttributeType::Numeric);

        $this->operandNumber = null === $operand ? null : (string) $operand;
    }

    public function getOperandDate(): ?\DateTimeImmutable
    {
        return $this->operandDate;
    }

    public function setOperandDate(?\DateTimeImmutable $operand): void
    {
        $this->assertType(AttributeType::Date);

        $this->operandDate = $operand;
    }

    public function getOperandBool(): ?bool
    {
        return $this->operandBool;
    }

    public function setOperandBool(?bool $operand): void
    {
        $this->assertType(AttributeType::Boolean);

        $this->operandBool = $operand;
    }

    /**
     * @return list<string>
     */
    public function getOperandOptions(): array
    {
        return $this->operandOptions ?? [];
    }

    /**
     * @param list<string> $operands
     */
    public function setOperandOptions(array $operands): void
    {
        $this->assertType(AttributeType::Select);

        foreach ($operands as $operand) {
            if (!$this->attribute->hasOption($operand)) {
                throw new \InvalidArgumentException(sprintf('Option "%s" is not defined for this attribute.', $operand));
            }
        }

        $this->operandOptions = array_values(array_unique($operands));
    }

    private function assertType(AttributeType ...$expected): void
    {
        if (!\in_array($this->getType(), $expected, true)) {
            throw new \LogicException(sprintf(
                'Attribute "%s" is of type "%s", which does not accept this operand.',
                $this->attribute->getName(),
                $this->getType()->value,
            ));
        }
    }
}
