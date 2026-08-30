<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: 'position_attribute')]
#[ORM\UniqueConstraint(name: 'uniq_position_attribute', columns: ['position_id', 'attribute_id'])]
class PositionAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    /**
     * No onDelete: the database must refuse to drop an attribute still in use.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Attribute $attribute;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * Section heading in the generated CV, falls back to the attribute category.
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $section = null;

    /**
     * Required attributes must be filled before the CV can be published.
     */
    #[ORM\Column]
    private bool $required = false;

    /**
     * Soft delete: CVs already generated from this position keep rendering the
     * attribute, it just stops being offered for new ones.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    public function __construct(Position $position, Attribute $attribute, int $sortOrder = 0)
    {
        $this->position  = $position;
        $this->attribute = $attribute;
        $this->sortOrder = $sortOrder;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): void
    {
        $this->section = $section;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function isRemoved(): bool
    {
        return null !== $this->removedAt;
    }

    public function getRemovedAt(): ?\DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function remove(): void
    {
        $this->removedAt ??= new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->removedAt = null;
    }
}
