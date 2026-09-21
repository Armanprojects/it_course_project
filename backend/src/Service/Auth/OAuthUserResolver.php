<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Enum\OAuthProvider;
use App\Exception\AuthException;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OAuthUserResolver
{
    public function __construct(
        private ClientRegistry $clients,
        private HttpClientInterface $http,
    ) {
    }

    public function client(OAuthProvider $provider): OAuth2Client
    {
        return $this->clients->getClient($provider->value);
    }

    /** @return list<string> the scopes to ask the provider for */
    public function scopes(OAuthProvider $provider): array
    {
        return match ($provider) {
            OAuthProvider::Google => ['openid', 'email', 'profile'],
            OAuthProvider::Github => ['read:user', 'user:email'],
        };
    }

    public function fetchUser(OAuthProvider $provider, string $code): OAuthUserData
    {
        $client = $this->client($provider);

        try {
            $token = $client->getAccessToken(['code' => $code]);
            $owner = $client->fetchUserFromToken($token);
        } catch (IdentityProviderException $e) {
            throw AuthException::providerFailed($e->getMessage());
        }

        return match ($provider) {
            OAuthProvider::Google => $this->fromGoogle($owner),
            OAuthProvider::Github => $this->fromGithub($owner, $token),
        };
    }

    private function fromGoogle(GoogleUser $owner): OAuthUserData
    {
        $email = $owner->isEmailTrustworthy() ? $owner->getEmail() : null;

        return new OAuthUserData(
            externalId: (string) $owner->getId(),
            email: $email,
            name: $owner->getName(),
            avatarUrl: $owner->getAvatar(),
        );
    }

    private function fromGithub(GithubResourceOwner $owner, AccessToken $token): OAuthUserData
    {
        $email = $owner->getEmail();

        return new OAuthUserData(
            externalId: (string) $owner->getId(),
            email: $email ?? $this->fetchGithubPrimaryEmail($token),
            name: $owner->getName() ?? $owner->getNickname(),
            avatarUrl: $owner->toArray()['avatar_url'] ?? null,
        );
    }

    private function fetchGithubPrimaryEmail(AccessToken $token): ?string
    {
        try {
            $response = $this->http->request('GET', 'https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getToken(),
                    'Accept'        => 'application/vnd.github+json',
                ],
            ]);

            /** @var list<array{email?: string, primary?: bool, verified?: bool}> $emails */
            $emails = $response->toArray();
        } catch (\Throwable) {
            return null;
        }

        foreach ($emails as $entry) {
            if (($entry['primary'] ?? false) && ($entry['verified'] ?? false)) {
                return $entry['email'] ?? null;
            }
        }

        foreach ($emails as $entry) {
            if ($entry['verified'] ?? false) {
                return $entry['email'] ?? null;
            }
        }

        return null;
    }
}
