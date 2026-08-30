<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * A post in the discussion tab of a position.
 *
 * Posts are append-only and shown in chronological order, so the id doubles as
 * the ordering key and as the cursor clients poll with ("give me posts after N").
 */
#[ORM\Entity]
#[ORM\Table(name: 'discussion_post')]
#[ORM\Index(name: 'idx_post_position_id', columns: ['position_id', 'id'])]
class DiscussionPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    /**
     * Kept nullable so that deleting a user does not tear holes in a discussion.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    /**
     * Markdown-formatted.
     */
    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Position $position, ?User $author, string $content)
    {
        $this->position  = $position;
        $this->author    = $author;
        $this->content   = $content;
        $this->createdAt = new \DateTimeImmutable();

        $position->addPost($this);
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
