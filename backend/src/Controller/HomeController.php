<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PositionRepository;
use App\Repository\TagRepository;
use App\Service\Position\PublicStatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Everything the landing page renders, in one public request.
 *
 * Four separate endpoints would mean four round trips for a page that is
 * always shown as a whole, and the home page is the first thing an anonymous
 * visitor loads — so it is served as a single payload.
 */
final class HomeController extends AbstractController
{
    private const LATEST_LIMIT   = 8;
    private const POPULAR_LIMIT  = 5;
    private const TAG_CLOUD_SIZE = 24;

    #[Route('/api/home', name: 'api_home', methods: ['GET'])]
    public function home(
        PositionRepository $positions,
        TagRepository $tags,
        PublicStatsProvider $stats,
    ): JsonResponse {
        return $this->json([
            'stats'           => $stats->collect(),
            'latestPositions' => $positions->findLatest(self::LATEST_LIMIT),
            'topPositions'    => $positions->findMostPopular(self::POPULAR_LIMIT),
            'tagCloud'        => $tags->findCloud(self::TAG_CLOUD_SIZE),
        ]);
    }
}
