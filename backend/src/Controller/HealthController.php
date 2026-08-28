<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Проверка живости стека: nginx -> php-fpm -> Symfony -> PostgreSQL.
 *
 * Нужна на этапе 1, чтобы убедиться, что все четыре звена связаны,
 * и остаётся дальше как endpoint для healthcheck хостинга.
 */
final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        return $this->json([
            'status'    => 'ok',
            'message'   => 'Hello world from Symfony',
            'php'       => PHP_VERSION,
            'database'  => $this->checkDatabase($connection),
            'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    /**
     * Отдельный метод, потому что недоступность БД не должна ронять весь
     * healthcheck 500-й ошибкой — приложение живо, БД отдельно помечена как down.
     */
    private function checkDatabase(Connection $connection): array
    {
        try {
            $version = $connection->fetchOne('SELECT version()');

            return [
                'connected' => true,
                'server'    => \is_string($version) ? $version : null,
            ];
        } catch (DbalException $e) {
            return [
                'connected' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }
}
