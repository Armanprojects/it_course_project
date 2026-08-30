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

/**
 * The OAuth leg is browser-driven: the user leaves for the provider and comes
 * back through a redirect, so neither endpoint can answer with JSON. The
 * callback hands the token to the SPA through the URL fragment instead.
 */
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

    /**
     * Where the picked role waits while the user is away at the provider.
     *
     * It cannot ride in "state": the bundle compares that value byte for byte
     * with what it stored, and reading the role back from a callback query
     * parameter would let anyone grant themselves a role by editing the URL.
     * The session is already in play here for the CSRF check.
     */
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

        // The user can decline on the provider's page; that is a normal outcome,
        // not an error worth a 500.
        if (null !== $request->query->get('error')) {
            return $this->redirectToFrontend(['error' => 'access_denied']);
        }

        $code = $request->query->getString('code');

        if ('' === $code) {
            return $this->redirectToFrontend(['error' => 'missing_code']);
        }

        // Consume it: the role is needed once, and a leftover value would be
        // applied to the next sign-in attempt that knows nothing about it.
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

    /**
     * An unknown value falls back to candidate instead of failing: the user has
     * already authenticated with the provider, and erroring out here would throw
     * away a successful sign-in over a malformed parameter.
     */
    private function resolveRole(mixed $value): SignupRole
    {
        return \is_string($value)
            ? SignupRole::tryFrom($value) ?? SignupRole::Candidate
            : SignupRole::Candidate;
    }

    /**
     * The payload rides in the fragment, not the query string: fragments are not
     * sent to servers and stay out of access logs, Referer headers and browser
     * history entries shared with the backend.
     *
     * @param array<string, string> $params
     */
    private function redirectToFrontend(array $params): RedirectResponse
    {
        return new RedirectResponse(
            rtrim($this->frontendUrl, '/') . '/auth/callback#' . http_build_query($params),
        );
    }
}
