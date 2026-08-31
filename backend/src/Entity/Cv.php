<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CvStatus;
use App\Repository\CvRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


/**
 * A CV is a candidate profile rendered through a position template.
 *
 * Attribute values are deliberately NOT stored here: the profile holds the only
 * master value, editing an attribute in a CV updates the profile itself.
 */
#[ORM\Entity(repositoryClass: CvRepository::class)]
#[ORM\Table(name: 'cv')]
#[ORM\UniqueConstraint(name: 'uniq_cv_profile_position', columns: ['profile_id', 'position_id'])]
#[ORM\Index(name: 'idx_cv_position_status', columns: ['position_id', 'status'])]
#[ORM\Index(name: 'idx_cv_created_at', columns: ['created_at'])]
class Cv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Profile $profile;

    #[ORM\ManyToOne(inversedBy: 'cvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\Column(length: 16, enumType: CvStatus::class)]
    private CvStatus $status = CvStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * Denormalised like counter: the CV tables and search results show it on
     * every row, counting per row would mean a query inside a loop.
     */
    #[ORM\Column]
    private int $likesCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /**
     * @var Collection<int, CvLike>
     */
    #[ORM\OneToMany(mappedBy: 'cv', targetEntity: CvLike::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $likes;

    public function __construct(Profile $profile, Position $position)
    {
        $this->profile   = $profile;
        $this->position  = $position;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->likes     = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    public function getCandidate(): User
    {
        return $this->profile->getUser();
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getStatus(): CvStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return CvStatus::Published === $this->status;
    }

    /**
     * Publishing is what makes the CV visible to recruiters, so it is only
     * allowed once every required attribute of the position carries a value.
     */
    public function publish(): void
    {
        if ($this->isPublished()) {
            return;
        }

        if (!$this->isComplete()) {
            throw new \LogicException('A CV can only be published once all its attributes are filled in.');
        }

        $this->status      = CvStatus::Published;
        $this->publishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->status      = CvStatus::Draft;
        $this->publishedAt = null;
        $this->touch();
    }

    /**
     * @return list<Attribute> attributes of the position left empty in the profile
     */
    public function getMissingAttributes(): array
    {
        $missing = [];

        foreach ($this->position->getAttributes() as $link) {
            $attribute = $link->getAttribute();
            $value     = $this->profile->getValueFor($attribute);

            if (null === $value || $value->isEmpty()) {
                $missing[] = $attribute;
            }
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return [] === $this->getMissingAttributes();
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
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
     * @return Collection<int, CvLike>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function isLikedBy(User $recruiter): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getRecruiter() === $recruiter) {
                return true;
            }
        }

        return false;
    }

    public function like(User $recruiter): ?CvLike
    {
        if ($this->isLikedBy($recruiter)) {
            return null;
        }

        $like = new CvLike($this, $recruiter);
        $this->likes->add($like);
        ++$this->likesCount;

        return $like;
    }

    public function unlike(User $recruiter): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getRecruiter() === $recruiter) {
                $this->likes->removeElement($like);
                $this->likesCount = max(0, $this->likesCount - 1);

                return true;
            }
        }

        return false;
    }

    /**
     * Projects of the candidate relevant to this position, trimmed to the limit
     * the position sets. An empty tag list on the position means "any project".
     *
     * @return list<Project>
     */
    public function getRelevantProjects(): array
    {
        $tagIds = array_values(array_filter(
            array_map(static fn (Tag $tag): ?int => $tag->getId(), $this->position->getProjectTags()->toArray()),
        ));

        $matched = [];

        foreach ($this->profile->getProjects() as $project) {
            if ([] === $tagIds || $project->matchesAnyTag($tagIds)) {
                $matched[] = $project;
            }
        }

        return \array_slice($matched, 0, $this->position->getMaxProjects());
    }
}
