<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: '`position`')]
#[ORM\Index(name: 'idx_position_public', columns: ['is_public'])]
class Position
{
    public const DEFAULT_MAX_PROJECTS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $level = null;

    /**
     * Public positions are open to every authenticated user,
     * otherwise access is decided by the rules below.
     */
    #[ORM\Column(name: 'is_public')]
    private bool $public = true;

    /**
     * Tags used to pick relevant candidate projects for the generated CV.
     * Empty means "any project qualifies".
     *
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'position_tag')]
    private Collection $projectTags;

    #[ORM\Column]
    private int $maxProjects = self::DEFAULT_MAX_PROJECTS;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /**
     * Full-text index over title, company and description.
     *
     * A generated column: PostgreSQL recomputes it on every write, so the
     * search index can never drift out of sync with the row the way an
     * application-maintained column would. Never written from PHP, hence
     * insertable/updatable false.
     */
    #[ORM\Column(
        name: 'search_vector',
        type: 'text',
        nullable: true,
        insertable: false,
        updatable: false,
        generated: 'ALWAYS',
    )]
    private ?string $searchVector = null;

    /**
     * Links are soft-deleted, so no orphanRemoval here.
     *
     * @var Collection<int, PositionAttribute>
     */
    #[ORM\OneToMany(mappedBy: 'position', targetEntity: PositionAttribute::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $attributes;

    /**
     * @var Collection<int, PositionAccessRule>
     */
    #[ORM\OneToMany(mappedBy: 'position', targetEntity: PositionAccessRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $accessRules;

    /**
     * @var Collection<int, Cv>
     */
    #[ORM\OneToMany(mappedBy: 'position', targetEntity: Cv::class)]
    private Collection $cvs;

    /**
     * @var Collection<int, DiscussionPost>
     */
    #[ORM\OneToMany(mappedBy: 'position', targetEntity: DiscussionPost::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $posts;

    public function __construct(string $title, ?User $createdBy = null)
    {
        $this->title       = $title;
        $this->createdBy   = $createdBy;
        $this->createdAt   = new \DateTimeImmutable();
        $this->updatedAt   = $this->createdAt;
        $this->attributes  = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
        $this->projectTags = new ArrayCollection();
        $this->cvs         = new ArrayCollection();
        $this->posts       = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): void
    {
        $this->level = $level;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getProjectTags(): Collection
    {
        return $this->projectTags;
    }

    public function addProjectTag(Tag $tag): void
    {
        if (!$this->projectTags->contains($tag)) {
            $this->projectTags->add($tag);
        }
    }

    public function removeProjectTag(Tag $tag): void
    {
        $this->projectTags->removeElement($tag);
    }

    public function getMaxProjects(): int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): void
    {
        if ($maxProjects < 0) {
            throw new \InvalidArgumentException('Maximum number of projects cannot be negative.');
        }

        $this->maxProjects = $maxProjects;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
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

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Active links only; soft-deleted ones stay readable via getAllAttributes().
     *
     * @return Collection<int, PositionAttribute>
     */
    public function getAttributes(): Collection
    {
        return $this->attributes->filter(
            static fn (PositionAttribute $link): bool => !$link->isRemoved(),
        );
    }

    /**
     * @return Collection<int, PositionAttribute>
     */
    public function getAllAttributes(): Collection
    {
        return $this->attributes;
    }

    public function getAttributeLink(Attribute $attribute): ?PositionAttribute
    {
        foreach ($this->attributes as $link) {
            if ($link->getAttribute() === $attribute) {
                return $link;
            }
        }

        return null;
    }

    public function addAttribute(Attribute $attribute, ?int $sortOrder = null): PositionAttribute
    {
        $link = $this->getAttributeLink($attribute);

        if (null !== $link) {
            $link->restore();

            return $link;
        }

        $link = new PositionAttribute($this, $attribute, $sortOrder ?? $this->getAttributes()->count());
        $this->attributes->add($link);

        return $link;
    }

    public function removeAttribute(Attribute $attribute): void
    {
        $this->getAttributeLink($attribute)?->remove();
    }

    public function hasAttribute(Attribute $attribute): bool
    {
        $link = $this->getAttributeLink($attribute);

        return null !== $link && !$link->isRemoved();
    }

    /**
     * @return Collection<int, PositionAccessRule>
     */
    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }

    public function addAccessRule(PositionAccessRule $rule): void
    {
        if (!$this->accessRules->contains($rule)) {
            $this->accessRules->add($rule);
        }
    }

    public function removeAccessRule(PositionAccessRule $rule): void
    {
        $this->accessRules->removeElement($rule);
    }

    /**
     * Lazy by design: the CV list of a position is paginated in its repository,
     * never walked through this collection.
     *
     * @return Collection<int, Cv>
     */
    public function getCvs(): Collection
    {
        return $this->cvs;
    }

    /**
     * @return Collection<int, DiscussionPost>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(DiscussionPost $post): void
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
        }
    }
}
