<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeType;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: 'attribute_value')]
#[ORM\UniqueConstraint(name: 'uniq_value_profile_attribute', columns: ['profile_id', 'attribute_id'])]
#[ORM\Index(name: 'idx_value_attribute_number', columns: ['attribute_id', 'value_number'])]
#[ORM\Index(name: 'idx_value_attribute_bool', columns: ['attribute_id', 'value_bool'])]
#[ORM\Index(name: 'idx_value_attribute_option', columns: ['attribute_id', 'value_option'])]
class AttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributeValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    /**
     * No onDelete: the database must refuse to drop an attribute still in use.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Attribute $attribute;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valueString = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $valueText = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $valueNumber = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $valueDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $valueDateEnd = null;

    #[ORM\Column(nullable: true)]
    private ?bool $valueBool = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valueOption = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $valueImageUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(Profile $profile, Attribute $attribute)
    {
        $this->profile   = $profile;
        $this->attribute = $attribute;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getType(): AttributeType
    {
        return $this->attribute->getType();
    }

    public function getValueString(): ?string
    {
        return $this->valueString;
    }

    public function setValueString(?string $value): void
    {
        $this->assertType(AttributeType::String);

        $this->valueString = $value;
        $this->touch();
    }

    public function getValueText(): ?string
    {
        return $this->valueText;
    }

    public function setValueText(?string $value): void
    {
        $this->assertType(AttributeType::Text);

        $this->valueText = $value;
        $this->touch();
    }

    public function getValueNumber(): ?string
    {
        return $this->valueNumber;
    }

    public function setValueNumber(string|int|float|null $value): void
    {
        $this->assertType(AttributeType::Numeric);

        $this->valueNumber = null === $value ? null : (string) $value;
        $this->touch();
    }

    public function getValueDate(): ?\DateTimeImmutable
    {
        return $this->valueDate;
    }

    public function setValueDate(?\DateTimeImmutable $value): void
    {
        $this->assertType(AttributeType::Date);

        $this->valueDate = $value;
        $this->touch();
    }

    public function getValueDateEnd(): ?\DateTimeImmutable
    {
        return $this->valueDateEnd;
    }

    public function setPeriod(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): void
    {
        $this->assertType(AttributeType::Period);

        if (null !== $from && null !== $to && $to < $from) {
            throw new \InvalidArgumentException('Period end cannot precede its start.');
        }

        $this->valueDate    = $from;
        $this->valueDateEnd = $to;
        $this->touch();
    }

    public function getValueBool(): ?bool
    {
        return $this->valueBool;
    }

    public function setValueBool(?bool $value): void
    {
        $this->assertType(AttributeType::Boolean);

        $this->valueBool = $value;
        $this->touch();
    }

    public function getValueOption(): ?string
    {
        return $this->valueOption;
    }

    public function setValueOption(?string $value): void
    {
        $this->assertType(AttributeType::Select);

        if (null !== $value && !$this->attribute->hasOption($value)) {
            throw new \InvalidArgumentException(sprintf('Option "%s" is not defined for this attribute.', $value));
        }

        $this->valueOption = $value;
        $this->touch();
    }

    public function getValueImageUrl(): ?string
    {
        return $this->valueImageUrl;
    }

    public function setValueImageUrl(?string $value): void
    {
        $this->assertType(AttributeType::Image);

        $this->valueImageUrl = $value;
        $this->touch();
    }

    public function isEmpty(): bool
    {
        return match ($this->getType()) {
            AttributeType::String  => null === $this->valueString || '' === $this->valueString,
            AttributeType::Text    => null === $this->valueText || '' === $this->valueText,
            AttributeType::Numeric => null === $this->valueNumber,
            AttributeType::Date    => null === $this->valueDate,
            AttributeType::Period  => null === $this->valueDate && null === $this->valueDateEnd,
            AttributeType::Boolean => null === $this->valueBool,
            AttributeType::Select  => null === $this->valueOption,
            AttributeType::Image   => null === $this->valueImageUrl,
        };
    }

    public function clear(): void
    {
        $this->valueString   = null;
        $this->valueText     = null;
        $this->valueNumber   = null;
        $this->valueDate     = null;
        $this->valueDateEnd  = null;
        $this->valueBool     = null;
        $this->valueOption   = null;
        $this->valueImageUrl = null;
        $this->touch();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function assertType(AttributeType $expected): void
    {
        if ($expected !== $this->getType()) {
            throw new \LogicException(sprintf(
                'Attribute "%s" is of type "%s", cannot store a "%s" value.',
                $this->attribute->getName(),
                $this->getType()->value,
                $expected->value,
            ));
        }
    }
}
