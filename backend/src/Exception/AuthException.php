<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Carries an HTTP status alongside a machine-readable code so the SPA can tell
 * "email taken" from "wrong password" without parsing prose.
 */
class AuthException extends HttpException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        int $statusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($statusCode, $message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function emailAlreadyUsed(): self
    {
        return new self(
            'An account with this email already exists.',
            'email_already_used',
            Response::HTTP_CONFLICT,
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'Invalid email or password.',
            'invalid_credentials',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function accountBlocked(): self
    {
        return new self(
            'This account has been blocked.',
            'account_blocked',
            Response::HTTP_FORBIDDEN,
        );
    }

    public static function passwordLoginUnavailable(): self
    {
        return new self(
            'This account uses social login only. Sign in with your provider instead.',
            'password_login_unavailable',
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public static function unknownProvider(string $provider): self
    {
        return new self(
            sprintf('Unknown authentication provider "%s".', $provider),
            'unknown_provider',
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function providerFailed(string $reason): self
    {
        return new self(
            sprintf('Social authentication failed: %s', $reason),
            'provider_failed',
            Response::HTTP_BAD_GATEWAY,
        );
    }

    public static function providerEmailMissing(): self
    {
        return new self(
            'The provider did not return a verified email address.',
            'provider_email_missing',
            Response::HTTP_BAD_REQUEST,
        );
    }

    public static function identityTakenByAnotherUser(): self
    {
        return new self(
            'This social account is already linked to a different user.',
            'identity_taken',
            Response::HTTP_CONFLICT,
        );
    }

    public static function emailNotVerified(): self
    {
        return new self(
            'Confirm your email address before signing in. Check your inbox for the link.',
            'email_not_verified',
            Response::HTTP_FORBIDDEN,
        );
    }

    public static function emailAlreadyVerified(): self
    {
        return new self(
            'This email address is already confirmed.',
            'email_already_verified',
            Response::HTTP_CONFLICT,
        );
    }

    public static function invalidVerificationToken(): self
    {
        return new self(
            'This confirmation link is not valid.',
            'invalid_verification_token',
            Response::HTTP_NOT_FOUND,
        );
    }

    public static function verificationTokenExpired(): self
    {
        return new self(
            'This confirmation link has expired. Request a new one.',
            'verification_token_expired',
            Response::HTTP_GONE,
        );
    }

    public static function verificationTokenUsed(): self
    {
        return new self(
            'This confirmation link has already been used.',
            'verification_token_used',
            Response::HTTP_GONE,
        );
    }

    public static function tooManyVerificationRequests(): self
    {
        return new self(
            'Too many confirmation emails requested. Try again later.',
            'too_many_verification_requests',
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    public static function verificationEmailFailed(string $reason): self
    {
        return new self(
            sprintf('Could not send the confirmation email: %s', $reason),
            'verification_email_failed',
            Response::HTTP_BAD_GATEWAY,
        );
    }
}
