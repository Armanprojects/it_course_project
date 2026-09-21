<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

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
