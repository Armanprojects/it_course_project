<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_name_normalized', columns: ['name_normalized'])]
#[ORM\Index(name: 'idx_tag_usage', columns: ['usage_count'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column(length: 64)]
    private string $nameNormalized;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->createdAt = new \DateTimeImmutable();

        $this->setName($name);
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
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
        $this->nameNormalized = self::normalize($name);
    }

    public function getNameNormalized(): string
    {
        return $this->nameNormalized;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function incrementUsage(): void
    {
        ++$this->usageCount;
    }

    public function decrementUsage(): void
    {
        $this->usageCount = max(0, $this->usageCount - 1);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
