<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Exception\AuthException;
use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Issues and redeems the one-time links that confirm a signup address.
 */
final readonly class EmailVerificationService
{
    /** How many links one account may request per hour. */
    private const RESEND_LIMIT = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private EmailVerificationTokenRepository $tokens,
        private MailerInterface $mailer,
        private string $frontendUrl,
        private string $senderAddress,
        private string $senderName,
    ) {
    }

    /**
     * Creates a fresh link and emails it. Any previous link stops working, so a
     * user who clicks an old message cannot activate an account they have since
     * asked to re-verify.
     */
    public function sendVerificationLink(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw AuthException::emailAlreadyVerified();
        }

        if ($this->tokens->countIssuedSince($user, new \DateTimeImmutable('-1 hour')) >= self::RESEND_LIMIT) {
            throw AuthException::tooManyVerificationRequests();
        }

        $this->tokens->invalidateAllFor($user);

        // Plaintext exists only here and in the email; the row keeps its hash.
        $plainToken = bin2hex(random_bytes(EmailVerificationToken::TOKEN_BYTES));

        $token = new EmailVerificationToken($user, $plainToken);
        $user->addVerificationToken($token);

        $this->em->persist($token);
        $this->em->flush();

        $this->send($user, $plainToken);
    }

    /**
     * Redeems a link. Every failure mode is distinguishable on purpose: an
     * expired link should offer a resend, a used one should not alarm anybody.
     */
    public function confirm(string $plainToken): User
    {
        $token = $this->tokens->findOneByPlainToken($plainToken);

        if (null === $token) {
            throw AuthException::invalidVerificationToken();
        }

        $user = $token->getUser();

        // An already verified account means the link was clicked twice, e.g. by
        // a mail client prefetching URLs. That is a success, not an error.
        if ($user->isEmailVerified()) {
            return $user;
        }

        if ($token->isUsed()) {
            throw AuthException::verificationTokenUsed();
        }

        if ($token->isExpired()) {
            throw AuthException::verificationTokenExpired();
        }

        $token->markUsed();
        $user->verifyEmail();

        $this->em->flush();

        return $user;
    }

    private function send(User $user, string $plainToken): void
    {
        $link = sprintf(
            '%s/auth/verify?token=%s',
            rtrim($this->frontendUrl, '/'),
            urlencode($plainToken),
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($user->getEmail())
            ->subject('Подтвердите регистрацию в CVMatch')
            ->htmlTemplate('email/verify.html.twig')
            ->context([
                'link'      => $link,
                'expiresIn' => '24 часа',
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // The account and its token already exist, so the user can ask for
            // another link; surfacing the failure lets the UI say so honestly.
            throw AuthException::verificationEmailFailed($e->getMessage());
        }
    }
}
