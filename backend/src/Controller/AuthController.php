<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LoginRequest;
use App\Dto\RegisterRequest;
use App\Dto\ResendVerificationRequest;
use App\Dto\VerifyEmailRequest;
use App\Entity\User;
use App\Exception\AuthException;
use App\Repository\UserRepository;
use App\Service\Auth\AuthenticationService;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\UserSerializer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly EmailVerificationService $verification,
        private readonly UserRepository $users,
        private readonly JWTTokenManagerInterface $tokens,
        private readonly UserSerializer $serializer,
    ) {
    }

    /**
     * Creates the account and mails the confirmation link. Deliberately returns
     * no token: the address is unproven until the link is opened, so there is
     * nothing to sign the user in with yet.
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $payload): JsonResponse
    {
        $user = $this->auth->register($payload->email, $payload->password, $payload->signupRole());

        $this->verification->sendVerificationLink($user);

        return $this->json([
            'status'  => 'verification_sent',
            'email'   => $user->getEmail(),
            'message' => 'Мы отправили ссылку для подтверждения на указанный адрес.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Opens the emailed link. Returns a token so that confirming the address
     * lands the user straight in the app instead of asking them to log in again.
     */
    #[Route('/verify', name: 'api_auth_verify', methods: ['POST'])]
    public function verify(#[MapRequestPayload] VerifyEmailRequest $payload): JsonResponse
    {
        $user = $this->verification->confirm($payload->token);

        return $this->tokenResponse($user);
    }

    /**
     * Always answers the same way, whether or not the address is registered:
     * a different response would turn this endpoint into a way to enumerate
     * which emails have accounts.
     */
    #[Route('/verify/resend', name: 'api_auth_verify_resend', methods: ['POST'])]
    public function resendVerification(#[MapRequestPayload] ResendVerificationRequest $payload): JsonResponse
    {
        $user = $this->users->findOneByEmail($payload->email);

        if (null !== $user && !$user->isEmailVerified()) {
            try {
                $this->verification->sendVerificationLink($user);
            } catch (AuthException $e) {
                // Rate limiting is the one thing worth reporting: staying silent
                // would leave the user retrying a button that cannot work yet.
                if ('too_many_verification_requests' === $e->getErrorCode()) {
                    throw $e;
                }
            }
        }

        return $this->json([
            'status'  => 'verification_sent',
            'message' => 'Если аккаунт с таким адресом существует и не подтверждён, мы отправили ссылку.',
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginRequest $payload): JsonResponse
    {
        $user = $this->auth->authenticate($payload->email, $payload->password);

        return $this->tokenResponse($user);
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->serializer->serialize($user));
    }

    /**
     * Stateless JWT cannot be revoked server-side, so logging out is the client
     * dropping its token. The endpoint exists so the SPA has one thing to call,
     * and so a future refresh-token blacklist has a place to live.
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    private function tokenResponse(User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'token' => $this->tokens->create($user),
            'user'  => $this->serializer->serialize($user),
        ], $status);
    }
}
