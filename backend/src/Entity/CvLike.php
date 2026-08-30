<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Only recruiters may like a CV, and at most once — enforced by the unique
 * constraint so that concurrent requests cannot slip a second row through.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cv_like')]
#[ORM\UniqueConstraint(name: 'uniq_like_cv_recruiter', columns: ['cv_id', 'recruiter_id'])]
class CvLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Cv $cv;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recruiter;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Cv $cv, User $recruiter)
    {
        $this->cv        = $cv;
        $this->recruiter = $recruiter;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCv(): Cv
    {
        return $this->cv;
    }

    public function setCv(Cv $cv): void
    {
        $this->cv = $cv;
    }

    public function getRecruiter(): User
    {
        return $this->recruiter;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
