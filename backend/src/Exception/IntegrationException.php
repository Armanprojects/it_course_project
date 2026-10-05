<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Сбой внешней интеграции (Salesforce, Dropbox).
 *
 * 502: виноват не клиент и не мы, а внешний сервис — фронтенд показывает
 * сообщение как есть, без маппинга на коды валидации.
 */
class IntegrationException extends HttpException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct(Response::HTTP_BAD_GATEWAY, $message, $previous);
    }
}
