<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Repository\AttributeRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: AttributeRepository::class)]
#[ORM\Table(name: 'attribute')]
#[ORM\UniqueConstraint(name: 'uniq_attribute_name_normalized', columns: ['name_normalized'])]
#[ORM\Index(name: 'idx_attribute_category', columns: ['category'])]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    /**
     * Lowercased name, keeps the "globally unique" rule case-insensitive.
     */
    #[ORM\Column(length: 120)]
    private string $nameNormalized;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 32, enumType: AttributeCategory::class)]
    private AttributeCategory $category;

    /**
     * Immutable: changing it would invalidate every stored value.
     */
    #[ORM\Column(length: 16, enumType: AttributeType::class)]
    private AttributeType $type;

    /**
     * Dropdown choices, only for AttributeType::Select.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    /**
     * Built-in attributes of the "Me" section, recruiters cannot delete them.
     */
    #[ORM\Column(name: 'is_system')]
    private bool $system = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Soft delete: stored values and position links survive, the attribute just
     * disappears from the library and cannot be picked any more.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $removedAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(
        string $name,
        AttributeCategory $category,
        AttributeType $type,
        ?User $createdBy = null,
    ) {
        $this->category  = $category;
        $this->type      = $type;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();

        $this->setName($name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name           = trim($name);
        $this->nameNormalized = mb_strtolower($this->name);
    }

    public function getNameNormalized(): string
    {
        return $this->nameNormalized;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCategory(): AttributeCategory
    {
        return $this->category;
    }

    public function setCategory(AttributeCategory $category): void
    {
        $this->category = $category;
    }

    public function getType(): AttributeType
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * @param list<string> $options
     */
    public function setOptions(array $options): void
    {
        if (!$this->type->requiresOptions()) {
            throw new \LogicException(sprintf('Attribute of type "%s" cannot have options.', $this->type->value));
        }

        $this->options = array_values(array_unique(array_map(trim(...), $options)));
    }

    public function hasOption(string $option): bool
    {
        return \in_array($option, $this->getOptions(), true);
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function markAsSystem(): void
    {
        $this->system = true;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
        if ($this->system) {
            throw new \LogicException('Built-in attributes cannot be removed.');
        }

        $this->removedAt ??= new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->removedAt = null;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
