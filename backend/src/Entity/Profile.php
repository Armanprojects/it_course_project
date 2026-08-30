<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: 'profile')]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;


    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * Master values of the attributes picked from the library, one row per attribute.
     *
     * @var Collection<int, AttributeValue>
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: AttributeValue::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributeValues;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Project::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $projects;

    /**
     * @var Collection<int, Cv>
     */
    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Cv::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cvs;

    public function __construct(User $user)
    {
        $this->user            = $user;
        $this->updatedAt       = new \DateTimeImmutable();
        $this->attributeValues = new ArrayCollection();
        $this->projects        = new ArrayCollection();
        $this->cvs             = new ArrayCollection();

        $user->setProfile($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getVersion(): int
    {
        return $this->version;
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
     * @return Collection<int, AttributeValue>
     */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    public function getValueFor(Attribute $attribute): ?AttributeValue
    {
        foreach ($this->attributeValues as $value) {
            if ($value->getAttribute() === $attribute) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Attaches the attribute to the profile, keeping the existing value if any.
     */
    public function addAttribute(Attribute $attribute): AttributeValue
    {
        $value = $this->getValueFor($attribute);

        if (null === $value) {
            $value = new AttributeValue($this, $attribute);
            $this->attributeValues->add($value);
            $this->touch();
        }

        return $value;
    }

    public function removeAttribute(Attribute $attribute): void
    {
        if ($attribute->isSystem()) {
            throw new \LogicException('Built-in attributes cannot be removed from a profile.');
        }

        $value = $this->getValueFor($attribute);

        if (null !== $value) {
            $this->attributeValues->removeElement($value);
            $this->touch();
        }
    }

    public function hasAttribute(Attribute $attribute): bool
    {
        return null !== $this->getValueFor($attribute);
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): void
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $this->touch();
        }
    }

    public function removeProject(Project $project): void
    {
        if ($this->projects->removeElement($project)) {
            $this->touch();
        }
    }

    /**
     * @return Collection<int, Cv>
     */
    public function getCvs(): Collection
    {
        return $this->cvs;
    }

    public function getCvFor(Position $position): ?Cv
    {
        foreach ($this->cvs as $cv) {
            if ($cv->getPosition() === $position) {
                return $cv;
            }
        }

        return null;
    }

    /**
     * At most one CV per position, so an existing one is returned as is.
     */
    public function startCv(Position $position): Cv
    {
        $cv = $this->getCvFor($position);

        if (null === $cv) {
            $cv = new Cv($this, $position);
            $this->cvs->add($cv);
        }

        return $cv;
    }
}
