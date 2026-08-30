<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * One-time link that proves the person controls the address they signed up with.
 *
 * Only a hash of the token is stored: the plaintext lives in the emailed URL and
 * nowhere else, so a leaked database does not let anyone confirm other accounts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_verification_token')]
#[ORM\UniqueConstraint(name: 'uniq_verification_hash', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_verification_user', columns: ['user_id'])]
class EmailVerificationToken
{
    /** Long enough that guessing is hopeless, short enough for a clean URL. */
    public const TOKEN_BYTES = 32;

    public const LIFETIME = '+24 hours';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'verificationTokens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** sha256 of the plaintext token; 64 hex characters. */
    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, string $plainToken)
    {
        $this->user      = $user;
        $this->tokenHash = self::hash($plainToken);
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(self::LIFETIME);
    }

    /**
     * Plain sha256, not a password hash: the token is 32 random bytes, so there
     * is nothing to brute-force, and lookup has to be a single indexed query.
     */
    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) > $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function isUsable(?\DateTimeImmutable $now = null): bool
    {
        return !$this->isUsed() && !$this->isExpired($now);
    }

    public function markUsed(): void
    {
        $this->usedAt ??= new \DateTimeImmutable();
    }
}
