<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MailDiagnosticsController extends AbstractController
{
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

        if (!hash_equals($this->diagnosticsToken, (string) $request->headers->get('X-Diagnostics-Token'))) {
            return $this->json(['error' => 'forbidden'], 403);
        }

        $parts  = parse_url($this->mailerDsn);
        $scheme = \is_array($parts) ? ($parts['scheme'] ?? null) : null;

        $report = [
            'config' => [

                'mailerDsnSet'    => '' !== $this->mailerDsn,
                'transport'       => $this->describeTransport($parts),
                'senderAddress'   => $this->senderAddress,
                'frontendUrl'     => $this->frontendUrl,
                'isNullTransport' => 'null' === $scheme,
            ],
            'connectivity' => $this->probe($parts),
            'limits'       => [

                'phpMaxExecutionTime' => (int) ini_get('max_execution_time'),
                'phpDefaultSocketTimeout' => (int) ini_get('default_socket_timeout'),
                'nginxFastcgiReadTimeout' => '60s (см. docker/nginx.prod.conf)',
            ],
        ];

        $this->mailLogger->info('mail diagnostics requested', $report);

        return $this->json($report);
    }

    private function probe(array|false $parts): array
    {
        if (!\is_array($parts) || !isset($parts['scheme'])) {
            return ['checked' => false, 'reason' => 'unparsable MAILER_DSN'];
        }

        if (str_contains($parts['scheme'], '+api') || str_contains($parts['scheme'], '+https')) {
            return $this->probeHttpApi($parts);
        }

        if (!isset($parts['host'])) {
            return ['checked' => false, 'reason' => 'no host in MAILER_DSN'];
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ('smtps' === ($parts['scheme'] ?? '') ? 465 : 587);

        $startedAt = microtime(true);

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

    private function probeHttpApi(array $parts): array
    {
        $host = match (true) {
            str_starts_with($parts['scheme'] ?? '', 'brevo') => 'api.brevo.com',
            default                                          => 'api.resend.com',
        };

        $startedAt = microtime(true);
        $socket    = @fsockopen('ssl://' . $host, 443, $errno, $errstr, self::PROBE_TIMEOUT_SECONDS);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (false !== $socket) {
            fclose($socket);

            return ['checked' => true, 'result' => 'ok', 'host' => $host, 'port' => 443, 'elapsedMs' => $elapsedMs];
        }

        return [
            'checked'   => true,
            'result'    => $this->classify($errno, $elapsedMs),
            'host'      => $host,
            'port'      => 443,
            'elapsedMs' => $elapsedMs,
            'errno'     => $errno,
            'error'     => $errstr,
        ];
    }

    private function classify(int $errno, int $elapsedMs): string
    {
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

        return sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'] ?? '(no-host)',
            isset($parts['port']) ? ':' . $parts['port'] : ':(default)',
        );
    }
}
