<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Position;
use App\Entity\PositionAttribute;
use App\Entity\Tag;
use App\Repository\PositionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only view of the position catalogue.
 *
 * Open to anonymous visitors on purpose: browsing positions without an account
 * is what the brief asks for. Everything that could identify a candidate — the
 * CVs submitted to a position, its discussion, its access rules — stays out of
 * these payloads and lives behind the authenticated endpoints.
 */
#[Route('/api/positions')]
final class PositionController extends AbstractController
{
    public function __construct(private readonly PositionRepository $positions)
    {
    }

    /**
     * Paginated, sortable, searchable positions table.
     */
    #[Route('', name: 'api_positions_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $query = $request->query;

        return $this->json($this->positions->findPage(
            $query->get('search'),
            (string) $query->get('sort', 'updatedAt'),
            (string) $query->get('direction', 'desc'),
            $query->getInt('page', 1),
            $query->getInt('pageSize', PositionRepository::DEFAULT_PAGE_SIZE),
        ));
    }

    /**
     * Read-only detail of one position: what a CV built from this template
     * would ask for. Anonymous visitors see the shape, not anybody's answers.
     */
    #[Route('/{id<\d+>}', name: 'api_positions_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $position = $this->positions->findDetail($id);

        if (null === $position) {
            throw $this->createNotFoundException('Position not found.');
        }

        return $this->json($this->serialize($position));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Position $position): array
    {
        $attributes = [];

        foreach ($position->getAttributes() as $link) {
            $attributes[] = $this->serializeAttribute($link);
        }

        $tags = [];

        foreach ($position->getProjectTags() as $tag) {
            $tags[] = $this->serializeTag($tag);
        }

        return [
            'id'               => $position->getId(),
            'title'            => $position->getTitle(),
            'shortDescription' => $position->getShortDescription(),
            'company'          => $position->getCompany(),
            'level'            => $position->getLevel(),
            'public'           => $position->isPublic(),
            'maxProjects'      => $position->getMaxProjects(),
            'createdAt'        => $position->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt'        => $position->getUpdatedAt()->format(\DATE_ATOM),
            'attributes'       => $attributes,
            'projectTags'      => $tags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttribute(PositionAttribute $link): array
    {
        $attribute = $link->getAttribute();

        return [
            'id'          => $attribute->getId(),
            'name'        => $attribute->getName(),
            'description' => $attribute->getDescription(),
            'category'    => $attribute->getCategory()->value,
            'type'        => $attribute->getType()->value,
            'options'     => $attribute->getOptions(),
            'section'     => $link->getSection(),
            'required'    => $link->isRequired(),
            'sortOrder'   => $link->getSortOrder(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTag(Tag $tag): array
    {
        return [
            'id'   => $tag->getId(),
            'name' => $tag->getName(),
        ];
    }
}
