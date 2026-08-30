<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OAuthProvider;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: 'user_identity')]
#[ORM\UniqueConstraint(name: 'uniq_identity_provider_external', columns: ['provider', 'external_id'])]
#[ORM\UniqueConstraint(name: 'uniq_identity_user_provider', columns: ['user_id', 'provider'])]
class UserIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'identities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32, enumType: OAuthProvider::class)]
    private OAuthProvider $provider;


    #[ORM\Column(length: 191)]
    private string $externalId;

    #[ORM\Column]
    private \DateTimeImmutable $linkedAt;

    public function __construct(User $user, OAuthProvider $provider, string $externalId)
    {
        $this->user       = $user;
        $this->provider   = $provider;
        $this->externalId = $externalId;
        $this->linkedAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getProvider(): OAuthProvider
    {
        return $this->provider;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getLinkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
