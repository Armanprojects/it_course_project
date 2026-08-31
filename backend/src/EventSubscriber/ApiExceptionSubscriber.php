<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\AuthException;
use App\Exception\ConflictException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Everything under /api answers with the same error envelope, so the SPA has one
 * shape to handle instead of Symfony's HTML error pages.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private bool $debug)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        $event->setResponse(match (true) {
            $exception instanceof ConflictException   => $this->conflictResponse($exception),
            $exception instanceof AuthException      => $this->authResponse($exception),
            $exception instanceof HttpExceptionInterface => $this->httpResponse($exception),
            default                                  => $this->serverErrorResponse($exception),
        });
    }

    /**
     * The version travels with the error: without it the client can only tell
     * the user "reload", instead of merging against what the server now holds.
     */
    private function conflictResponse(ConflictException $exception): JsonResponse
    {
        return new JsonResponse([
            'error'          => $exception->getErrorCode(),
            'message'        => $exception->getMessage(),
            'currentVersion' => $exception->getCurrentVersion(),
        ], $exception->getStatusCode());
    }

    private function authResponse(AuthException $exception): JsonResponse
    {
        return new JsonResponse([
            'error'   => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
        ], $exception->getStatusCode());
    }

    private function httpResponse(HttpExceptionInterface $exception): JsonResponse
    {
        $violations = $this->extractViolations($exception);

        $payload = [
            'error'   => $violations ? 'validation_failed' : 'http_error',
            'message' => $exception->getMessage(),
        ];

        if ($violations) {
            $payload['violations'] = $violations;
        }

        return new JsonResponse($payload, $exception->getStatusCode(), $exception->getHeaders());
    }

    /**
     * #[MapRequestPayload] wraps validation failures in an HTTP exception; dig
     * the field errors out so the client can highlight the offending inputs.
     *
     * @return array<string, string>
     */
    private function extractViolations(HttpExceptionInterface $exception): array
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof ValidationFailedException) {
            return [];
        }

        $violations = [];

        foreach ($previous->getViolations() as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $violations;
    }

    private function serverErrorResponse(\Throwable $exception): JsonResponse
    {
        $payload = [
            'error'   => 'internal_error',
            'message' => 'An unexpected error occurred.',
        ];

        // Never leak internals in production; in dev the message is what makes
        // the failure debuggable from the browser's network tab.
        if ($this->debug) {
            $payload['message']   = $exception->getMessage();
            $payload['exception'] = $exception::class;
        }

        return new JsonResponse($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
