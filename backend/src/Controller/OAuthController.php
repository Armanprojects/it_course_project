<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\OAuthProvider;
use App\Enum\SignupRole;
use App\Exception\AuthException;
use App\Service\Auth\AuthenticationService;
use App\Service\Auth\OAuthUserResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/oauth')]
final class OAuthController extends AbstractController
{
    public function __construct(
        private readonly OAuthUserResolver $resolver,
        private readonly AuthenticationService $auth,
        private readonly JWTTokenManagerInterface $tokens,
        private readonly string $frontendUrl,
    ) {
    }

    private const ROLE_SESSION_KEY = 'oauth_signup_role';

    #[Route('/{provider}', name: 'api_auth_oauth_start', methods: ['GET'])]
    public function start(string $provider, Request $request): RedirectResponse
    {
        $enum = $this->resolveProvider($provider);

        $request->getSession()->set(
            self::ROLE_SESSION_KEY,
            ($this->resolveRole($request->query->getString('role')))->value,
        );

        return $this->resolver
            ->client($enum)
            ->redirect($this->resolver->scopes($enum), []);
    }

    #[Route('/{provider}/callback', name: 'api_auth_oauth_callback', methods: ['GET'])]
    public function callback(string $provider, Request $request): RedirectResponse
    {
        $enum = $this->resolveProvider($provider);

        if (null !== $request->query->get('error')) {
            return $this->redirectToFrontend(['error' => 'access_denied']);
        }

        $code = $request->query->getString('code');

        if ('' === $code) {
            return $this->redirectToFrontend(['error' => 'missing_code']);
        }

        $session = $request->getSession();
        $role    = $this->resolveRole($session->remove(self::ROLE_SESSION_KEY));

        try {
            $data = $this->resolver->fetchUser($enum, $code);
            $user = $this->auth->authenticateWithProvider(
                $enum,
                $data->externalId,
                $data->email,
                $role,
            );
        } catch (AuthException $e) {
            return $this->redirectToFrontend(['error' => $e->getErrorCode()]);
        }

        return $this->redirectToFrontend(['token' => $this->tokens->create($user)]);
    }

    private function resolveProvider(string $provider): OAuthProvider
    {
        return OAuthProvider::tryFrom($provider)
            ?? throw AuthException::unknownProvider($provider);
    }

    private function resolveRole(mixed $value): SignupRole
    {
        return \is_string($value)
            ? SignupRole::tryFrom($value) ?? SignupRole::Candidate
            : SignupRole::Candidate;
    }

    /** @param array<string, string> $params */
    private function redirectToFrontend(array $params): RedirectResponse
    {
        return new RedirectResponse(
            rtrim($this->frontendUrl, '/') . '/auth/callback#' . http_build_query($params),
        );
    }
}
