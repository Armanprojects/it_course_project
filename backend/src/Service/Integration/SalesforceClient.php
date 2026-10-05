<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\Exception\IntegrationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Минимальный клиент Salesforce REST API.
 *
 * Авторизация — OAuth 2.0 Client Credentials Flow внешнего приложения
 * (Connected App): интеграция серверная, пользователь Salesforce в ней
 * не участвует, поэтому ни редиректов, ни хранения refresh-токена не нужно.
 * Токен живёт в пределах одного HTTP-запроса приложения — для действия
 * «создать Account + Contact» этого достаточно, кэшировать его негде и незачем.
 */
final class SalesforceClient
{
    private const TIMEOUT_SECONDS = 15;

    /** @var array{token: string, instanceUrl: string}|null */
    private ?array $session = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        private readonly string $loginUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiVersion,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->loginUrl)
            && '' !== trim($this->clientId)
            && '' !== trim($this->clientSecret);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return string id созданной записи
     */
    public function createAccount(array $fields): string
    {
        return $this->create('Account', $fields);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return string id созданной записи
     */
    public function createContact(array $fields): string
    {
        return $this->create('Contact', $fields);
    }

    /** Адрес записи в веб-интерфейсе Salesforce — для ссылки из ответа API. */
    public function recordUrl(string $id): string
    {
        return rtrim($this->session()['instanceUrl'], '/') . '/' . $id;
    }

    /** @param array<string, mixed> $fields */
    private function create(string $object, array $fields): string
    {
        $session = $this->session();

        try {
            $response = $this->http->request('POST', sprintf(
                '%s/services/data/%s/sobjects/%s',
                rtrim($session['instanceUrl'], '/'),
                $this->apiVersion,
                $object,
            ), [
                'auth_bearer' => $session['token'],
                'json'        => $fields,
                'timeout'     => self::TIMEOUT_SECONDS,
            ]);

            $status  = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Salesforce request failed.', [
                'object'    => $object,
                'exception' => $exception->getMessage(),
            ]);

            throw new IntegrationException('Salesforce недоступен, попробуйте позже.', $exception);
        }

        if (201 !== $status) {
            // Тело ошибки — массив вида [{"message": ..., "errorCode": ...}].
            $detail = \is_array($payload[0] ?? null) ? (string) ($payload[0]['message'] ?? '') : '';

            $this->logger->error('Salesforce rejected the record.', [
                'object' => $object,
                'status' => $status,
                'body'   => $payload,
            ]);

            throw new IntegrationException(
                '' !== $detail
                    ? sprintf('Salesforce отклонил запись (%s): %s', $object, $detail)
                    : sprintf('Salesforce отклонил запись (%s).', $object),
            );
        }

        return (string) $payload['id'];
    }

    /** @return array{token: string, instanceUrl: string} */
    private function session(): array
    {
        if (null !== $this->session) {
            return $this->session;
        }

        if (!$this->isConfigured()) {
            throw new IntegrationException('Интеграция с Salesforce не настроена на сервере.');
        }

        try {
            $response = $this->http->request('POST', rtrim($this->loginUrl, '/') . '/services/oauth2/token', [
                'body' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $status  = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Salesforce token request failed.', ['exception' => $exception->getMessage()]);

            throw new IntegrationException('Не удалось связаться с Salesforce.', $exception);
        }

        if (200 !== $status || !isset($payload['access_token'], $payload['instance_url'])) {
            $this->logger->error('Salesforce refused to issue a token.', ['status' => $status, 'body' => $payload]);

            throw new IntegrationException('Salesforce не принял учётные данные интеграции.');
        }

        return $this->session = [
            'token'       => (string) $payload['access_token'],
            'instanceUrl' => (string) $payload['instance_url'],
        ];
    }
}
