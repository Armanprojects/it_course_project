<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\Exception\IntegrationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Загрузка файлов в Dropbox (App Folder).
 *
 * Авторизация двумя способами:
 *  - DROPBOX_REFRESH_TOKEN + ключи приложения — рабочий вариант: access-токены
 *    Dropbox живут 4 часа, поэтому перед загрузкой токен обменивается заново;
 *  - DROPBOX_ACCESS_TOKEN — короткоживущий токен из App Console, годится
 *    только чтобы быстро проверить интеграцию.
 */
final class DropboxClient
{
    private const TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        private readonly string $accessToken,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $refreshToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->accessToken) || $this->hasRefreshFlow();
    }

    /**
     * @param string $path путь внутри папки приложения, начинается с "/"
     *
     * @return string путь сохранённого файла, как его видит Dropbox
     */
    public function upload(string $path, string $content): string
    {
        try {
            $response = $this->http->request('POST', 'https://content.dropboxapi.com/2/files/upload', [
                'auth_bearer' => $this->token(),
                'headers'     => [
                    'Content-Type'    => 'application/octet-stream',
                    // Не-ASCII в этом заголовке Dropbox требует кодировать \uXXXX.
                    'Dropbox-API-Arg' => json_encode([
                        'path'     => $path,
                        'mode'     => 'add',
                        'autorename' => true,
                    ], \JSON_THROW_ON_ERROR),
                ],
                'body'    => $content,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $status  = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (\JsonException | ExceptionInterface $exception) {
            $this->logger->error('Dropbox upload failed.', ['exception' => $exception->getMessage()]);

            throw new IntegrationException('Dropbox недоступен, попробуйте позже.', $exception);
        }

        if (200 !== $status) {
            $this->logger->error('Dropbox rejected the upload.', ['status' => $status, 'body' => $payload]);

            throw new IntegrationException('Dropbox не принял файл тикета.');
        }

        return (string) ($payload['path_display'] ?? $path);
    }

    private function hasRefreshFlow(): bool
    {
        return '' !== trim($this->refreshToken)
            && '' !== trim($this->appKey)
            && '' !== trim($this->appSecret);
    }

    private function token(): string
    {
        if (!$this->hasRefreshFlow()) {
            if ('' === trim($this->accessToken)) {
                throw new IntegrationException('Интеграция с Dropbox не настроена на сервере.');
            }

            return $this->accessToken;
        }

        try {
            $response = $this->http->request('POST', 'https://api.dropboxapi.com/oauth2/token', [
                'body' => [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'client_id'     => $this->appKey,
                    'client_secret' => $this->appSecret,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $status  = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Dropbox token refresh failed.', ['exception' => $exception->getMessage()]);

            throw new IntegrationException('Не удалось связаться с Dropbox.', $exception);
        }

        if (200 !== $status || !isset($payload['access_token'])) {
            $this->logger->error('Dropbox refused to refresh the token.', ['status' => $status, 'body' => $payload]);

            throw new IntegrationException('Dropbox не принял учётные данные интеграции.');
        }

        return (string) $payload['access_token'];
    }
}
