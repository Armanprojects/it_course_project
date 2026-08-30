<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Диагностика почты: отвечает на вопрос «почему /api/auth/verify/resend
 * отдаёт 504», не заставляя регистрировать пользователя ради проверки.
 *
 * Что происходит при 504: MAILER_DSN указывает на SMTP-сервер, отправка
 * идёт синхронно внутри HTTP-запроса, а на бесплатном плане Render
 * исходящие порты 25/465/587 закрыты. Соединение не отвергается — оно
 * висит до таймаута. nginx с fastcgi_read_timeout 60s срабатывает раньше
 * и отдаёт свою HTML-страницу 504, в которой нет поля message, поэтому
 * фронтенд показывает «Сервер недоступен» (см. toRequestError в
 * frontend/src/api/client.ts).
 *
 * Endpoint проверяет TCP-связность до SMTP-хоста с коротким таймаутом,
 * так что сам он 504 не вызывает и отвечает за считанные секунды.
 *
 * Доступ закрыт токеном: endpoint раскрывает адрес и порт почтового
 * сервера. Работает только если задана переменная DIAGNOSTICS_TOKEN,
 * и её же значение нужно передать в заголовке X-Diagnostics-Token.
 */
final class MailDiagnosticsController extends AbstractController
{
    /** Короткий таймаут: это проверка связности, а не отправка письма. */
    private const PROBE_TIMEOUT_SECONDS = 5.0;

    public function __construct(
        private readonly LoggerInterface $mailLogger,
        private readonly string $mailerDsn,
        private readonly string $senderAddress,
        private readonly string $frontendUrl,
        private readonly string $diagnosticsToken,
    ) {
    }

    #[Route('/api/diagnostics/mail', name: 'api_diagnostics_mail', methods: ['GET'])]
    public function mail(Request $request): JsonResponse
    {
        if ('' === $this->diagnosticsToken) {
            return $this->json(['error' => 'diagnostics_disabled'], 404);
        }

        // hash_equals: сравнение за постоянное время, чтобы токен нельзя
        // было подобрать по времени ответа.
        if (!hash_equals($this->diagnosticsToken, (string) $request->headers->get('X-Diagnostics-Token'))) {
            return $this->json(['error' => 'forbidden'], 403);
        }

        $parts  = parse_url($this->mailerDsn);
        $scheme = \is_array($parts) ? ($parts['scheme'] ?? null) : null;

        $report = [
            'config' => [
                // Виден ли вообще MAILER_DSN контейнеру. Незаданная переменная —
                // самая частая причина: Symfony падает уже на разборе DSN.
                'mailerDsnSet'    => '' !== $this->mailerDsn,
                'transport'       => $this->describeTransport($parts),
                'senderAddress'   => $this->senderAddress,
                'frontendUrl'     => $this->frontendUrl,
                // null:// значит письма молча выбрасываются: 504 не будет,
                // но и письмо не придёт. Отдельный, часто путаемый случай.
                'isNullTransport' => 'null' === $scheme,
            ],
            'connectivity' => $this->probe($parts),
            'limits'       => [
                // Сравните с elapsedMs в логах канала mail: если отправка
                // упирается в одно из этих чисел — виноват таймаут, а не SMTP.
                'phpMaxExecutionTime' => (int) ini_get('max_execution_time'),
                'phpDefaultSocketTimeout' => (int) ini_get('default_socket_timeout'),
                'nginxFastcgiReadTimeout' => '60s (см. docker/nginx.prod.conf)',
            ],
        ];

        $this->mailLogger->info('mail diagnostics requested', $report);

        return $this->json($report);
    }

    /**
     * Пробует открыть TCP-соединение до SMTP-хоста.
     *
     * Как читать результат:
     *   ok            — порт открыт, значит дело в учётных данных или в самом
     *                   SMTP-диалоге, смотрите causes в логах канала mail;
     *   timeout       — пакеты уходят в никуда: порт режет хостинг (типично
     *                   для бесплатного плана Render), нужен HTTP API почты;
     *   refused       — хост есть, но порт закрыт — скорее всего не тот порт;
     *   dns           — хост не резолвится, опечатка в MAILER_DSN.
     */
    private function probe(array|false $parts): array
    {
        if (!\is_array($parts) || !isset($parts['host'])) {
            return ['checked' => false, 'reason' => 'no host in MAILER_DSN'];
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ('smtps' === ($parts['scheme'] ?? '') ? 465 : 587);

        $startedAt = microtime(true);
        // Подавляем предупреждение: неудача здесь — ожидаемый результат
        // проверки, а не ошибка приложения; детали берём из $errno/$errstr.
        $socket    = @fsockopen($host, $port, $errno, $errstr, self::PROBE_TIMEOUT_SECONDS);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (false !== $socket) {
            fclose($socket);

            return ['checked' => true, 'result' => 'ok', 'host' => $host, 'port' => $port, 'elapsedMs' => $elapsedMs];
        }

        return [
            'checked'   => true,
            'result'    => $this->classify($errno, $elapsedMs),
            'host'      => $host,
            'port'      => $port,
            'elapsedMs' => $elapsedMs,
            'errno'     => $errno,
            'error'     => $errstr,
        ];
    }

    /**
     * Отличает «пакеты уходят в никуда» от «хост ответил отказом»:
     * это и есть развилка между блокировкой порта у хостинга и
     * ошибкой в настройках.
     */
    private function classify(int $errno, int $elapsedMs): string
    {
        // Соединение висело почти весь таймаут — ответа не было вовсе.
        if ($elapsedMs >= (int) (self::PROBE_TIMEOUT_SECONDS * 1000) - 200) {
            return 'timeout (порт, похоже, блокируется хостингом)';
        }

        return match ($errno) {
            111    => 'refused (хост доступен, порт закрыт)',
            0, 2   => 'dns (хост не резолвится)',
            default => 'error',
        };
    }

    private function describeTransport(array|false $parts): string
    {
        if (!\is_array($parts) || !isset($parts['scheme'])) {
            return 'unparsable-dsn';
        }

        // Логин и пароль в ответ не попадают.
        return sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'] ?? '(no-host)',
            isset($parts['port']) ? ':' . $parts['port'] : ':(default)',
        );
    }
}
