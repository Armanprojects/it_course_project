<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Autocomplete for the project tag input.
 */
#[Route('/api/tags')]
final class TagController extends AbstractController
{
    #[Route('/suggest', name: 'api_tags_suggest', methods: ['GET'])]
    public function suggest(Request $request, TagRepository $tags): JsonResponse
    {
        return $this->json([
            'items' => $tags->suggest($request->query->get('q'), 10),
        ]);
    }
}
