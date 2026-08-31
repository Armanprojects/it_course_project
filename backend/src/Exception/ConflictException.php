<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Someone else saved the record between the client reading it and writing it
 * back. Carries the version the server actually holds, so the SPA can tell the
 * user what happened and reload without guessing.
 */
final class ConflictException extends HttpException
{
    public function __construct(
        private readonly int $currentVersion,
        string $message = 'Эти данные изменились в другой вкладке или на другом устройстве.',
    ) {
        parent::__construct(Response::HTTP_CONFLICT, $message);
    }

    public function getErrorCode(): string
    {
        return 'version_conflict';
    }

    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }
}
