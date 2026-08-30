<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Locale;
use App\Enum\Theme;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\Index(name: 'idx_user_status', columns: ['status'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;


    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 16, enumType: UserStatus::class)]
    private UserStatus $status = UserStatus::Active;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(length: 8, enumType: Locale::class)]
    private Locale $locale = Locale::En;

    #[ORM\Column(length: 8, enumType: Theme::class)]
    private Theme $theme = Theme::Light;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;


    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Profile::class, cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserIdentity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $identities;

    /**
     * @var Collection<int, EmailVerificationToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EmailVerificationToken::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $verificationTokens;

    public function __construct(string $email, UserRole $role = UserRole::Candidate)
    {
        $this->email              = $email;
        $this->createdAt          = new \DateTimeImmutable();
        $this->identities         = new ArrayCollection();
        $this->verificationTokens = new ArrayCollection();

        $this->roles = [$role->value];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getRoles(): array
    {

        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function hasRole(UserRole $role): bool
    {
        return \in_array($role->value, $this->roles, true);
    }

    public function grantRole(UserRole $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role->value;
        }
    }


    public function revokeRole(UserRole $role): void
    {
        $this->roles = array_values(
            array_filter($this->roles, static fn (string $r): bool => $r !== $role->value),
        );
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function block(): void
    {
        $this->status = UserStatus::Blocked;
    }

    public function unblock(): void
    {
        $this->status = UserStatus::Active;
    }

    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    public function isPending(): bool
    {
        return UserStatus::Pending === $this->status;
    }

    /**
     * Parks a freshly registered account until its address is confirmed.
     * Not the field default: accounts created through a provider are usable at
     * once, and a default of Pending would silently lock them out.
     */
    public function markPending(): void
    {
        $this->status = UserStatus::Pending;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /**
     * Confirming the address is what turns a pending signup into a usable
     * account. A blocked user stays blocked: an admin ban outranks the link.
     */
    public function verifyEmail(): void
    {
        $this->emailVerifiedAt ??= new \DateTimeImmutable();

        if (UserStatus::Pending === $this->status) {
            $this->status = UserStatus::Active;
        }
    }

    /**
     * @return Collection<int, EmailVerificationToken>
     */
    public function getVerificationTokens(): Collection
    {
        return $this->verificationTokens;
    }

    public function addVerificationToken(EmailVerificationToken $token): void
    {
        if (!$this->verificationTokens->contains($token)) {
            $this->verificationTokens->add($token);
        }
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function touchLastLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function getIdentities(): Collection
    {
        return $this->identities;
    }

    public function addIdentity(UserIdentity $identity): void
    {
        if (!$this->identities->contains($identity)) {
            $this->identities->add($identity);
            $identity->setUser($this);
        }
    }

    public function eraseCredentials(): void
    {
    }
}
