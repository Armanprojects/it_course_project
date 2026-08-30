<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: 'project')]
#[ORM\Index(name: 'idx_project_profile_order', columns: ['profile_id', 'sort_order'])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodFrom = null;

    /**
     * Null means the project is still running.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodTo = null;

    /**
     * Markdown-formatted.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'project_tag')]
    private Collection $tags;

    public function __construct(Profile $profile, string $name)
    {
        $this->profile   = $profile;
        $this->name      = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->tags      = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getPeriodFrom(): ?\DateTimeImmutable
    {
        return $this->periodFrom;
    }

    public function getPeriodTo(): ?\DateTimeImmutable
    {
        return $this->periodTo;
    }

    public function setPeriod(?\DateTimeImmutable $from, ?\DateTimeImmutable $to): void
    {
        if (null !== $from && null !== $to && $to < $from) {
            throw new \InvalidArgumentException('Project end cannot precede its start.');
        }

        $this->periodFrom = $from;
        $this->periodTo   = $to;
        $this->touch();
    }

    public function isOngoing(): bool
    {
        return null !== $this->periodFrom && null === $this->periodTo;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * Usage counters are maintained by the caller, the entity only owns the link.
     */
    public function addTag(Tag $tag): bool
    {
        if ($this->tags->contains($tag)) {
            return false;
        }

        $this->tags->add($tag);
        $this->touch();

        return true;
    }

    public function removeTag(Tag $tag): bool
    {
        if (!$this->tags->removeElement($tag)) {
            return false;
        }

        $this->touch();

        return true;
    }

    public function hasTag(Tag $tag): bool
    {
        return $this->tags->contains($tag);
    }

    /**
     * True when the project matches any of the tags a position is interested in.
     *
     * @param list<int> $tagIds
     */
    public function matchesAnyTag(array $tagIds): bool
    {
        foreach ($this->tags as $tag) {
            if (\in_array($tag->getId(), $tagIds, true)) {
                return true;
            }
        }

        return false;
    }
}
