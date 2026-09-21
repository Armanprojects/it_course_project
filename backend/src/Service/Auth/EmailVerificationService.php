<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Exception\AuthException;
use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class EmailVerificationService
{
    private const RESEND_LIMIT = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private EmailVerificationTokenRepository $tokens,
        private MailerInterface $mailer,
        private LoggerInterface $mailLogger,
        private string $frontendUrl,
        private string $senderAddress,
        private string $senderName,
        private string $mailerDsn,
    ) {
    }

    public function sendVerificationLink(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw AuthException::emailAlreadyVerified();
        }

        if ($this->tokens->countIssuedSince($user, new \DateTimeImmutable('-1 hour')) >= self::RESEND_LIMIT) {
            throw AuthException::tooManyVerificationRequests();
        }

        $this->tokens->invalidateAllFor($user);

        $plainToken = bin2hex(random_bytes(EmailVerificationToken::TOKEN_BYTES));

        $token = new EmailVerificationToken($user, $plainToken);
        $user->addVerificationToken($token);

        $this->em->persist($token);
        $this->em->flush();

        $this->send($user, $plainToken);
    }

    public function confirm(string $plainToken): User
    {
        $token = $this->tokens->findOneByPlainToken($plainToken);

        if (null === $token) {
            throw AuthException::invalidVerificationToken();
        }

        $user = $token->getUser();

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

        $startedAt = microtime(true);

        $this->mailLogger->info('verification email: sending', [
            'recipient' => $this->maskEmail($user->getEmail()),
            'transport' => $this->describeTransport(),
        ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->mailLogger->error('verification email: failed', [
                'recipient' => $this->maskEmail($user->getEmail()),
                'transport' => $this->describeTransport(),
                'elapsedMs' => $this->elapsedMs($startedAt),
                'exception' => $e::class,
                'reason'    => $e->getMessage(),
                'causes'    => $this->causeChain($e),
            ]);

            throw AuthException::verificationEmailFailed($e->getMessage());
        }

        $this->mailLogger->info('verification email: sent', [
            'recipient' => $this->maskEmail($user->getEmail()),
            'elapsedMs' => $this->elapsedMs($startedAt),
        ]);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /** @return list<string> */
    private function causeChain(\Throwable $error): array
    {
        $causes   = [];
        $previous = $error->getPrevious();

        while (null !== $previous && \count($causes) < 5) {
            $causes[] = $previous::class . ': ' . $previous->getMessage();
            $previous = $previous->getPrevious();
        }

        return $causes;
    }

    private function describeTransport(): string
    {
        $parts = parse_url($this->mailerDsn);

        if (false === $parts || !isset($parts['scheme'])) {
            return 'unparsable-dsn';
        }

        return sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'] ?? '(no-host)',
            isset($parts['port']) ? ':' . $parts['port'] : ':(default)',
        );
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ('' === $domain || mb_strlen($local) < 3) {
            return '***@' . $domain;
        }

        return mb_substr($local, 0, 1) . '***' . mb_substr($local, -1) . '@' . $domain;
    }
}
