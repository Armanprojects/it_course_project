<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SavePositionRequest;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\User;
use App\Enum\AttributeType;
use App\Enum\FilterOperator;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Service\Cv\CvSerializer;
use App\Service\Position\PositionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Everything a recruiter does to a position.
 *
 * ROLE_RECRUITER guards the whole controller, and administrators inherit it
 * through the role hierarchy. There is no per-position ownership: the brief
 * states that all recruiters share one catalogue.
 */
#[Route('/api/positions')]
#[IsGranted('ROLE_RECRUITER')]
final class PositionAdminController extends AbstractController
{
    public function __construct(
        private readonly PositionRepository $positions,
        private readonly PositionService $service,
    ) {
    }

    #[Route('', name: 'api_positions_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SavePositionRequest $payload,
    ): JsonResponse {
        $position = $this->service->create($payload, $user);

        return $this->json($this->serialize($position), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_positions_update', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] SavePositionRequest $payload,
    ): JsonResponse {
        $position = $this->service->update($this->find($id), $payload);

        return $this->json($this->serialize($position));
    }

    #[Route('/{id<\d+>}/duplicate', name: 'api_positions_duplicate', methods: ['POST'])]
    public function duplicate(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $copy = $this->service->duplicate($this->find($id), $user);

        return $this->json($this->serialize($copy), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_positions_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($this->find($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * The full editable shape of a position: the read-only endpoint omits
     * access rules on purpose, and an editor needs them.
     */
    #[Route('/{id<\d+>}/edit', name: 'api_positions_edit', methods: ['GET'])]
    public function edit(int $id): JsonResponse
    {
        return $this->json($this->serialize($this->find($id)));
    }

    /**
     * CVs submitted to this position — recruiters and admins only.
     */
    #[Route('/{id<\d+>}/cvs', name: 'api_positions_cvs', methods: ['GET'])]
    public function cvs(
        int $id,
        Request $request,
        CvRepository $cvs,
        CvSerializer $serializer,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $position = $this->find($id);

        // Drafts stay hidden by default: publishing is what the brief makes
        // the moment a CV becomes visible to recruiters.
        $includeDrafts = $request->query->getBoolean('drafts');

        $rows = array_map(
            static fn ($cv): array => $serializer->serializeRow($cv, $user),
            $cvs->findForPosition($position, !$includeDrafts),
        );

        return $this->json(['items' => $rows, 'total' => \count($rows)]);
    }

    /**
     * Which filter operators each attribute type accepts — the rule editor
     * needs it to offer only valid combinations.
     */
    #[Route('/meta/operators', name: 'api_positions_operators', methods: ['GET'])]
    public function operators(): JsonResponse
    {
        $byType = [];

        foreach (AttributeType::cases() as $type) {
            $byType[$type->value] = array_map(
                static fn (FilterOperator $operator): string => $operator->value,
                $type->supportedOperators(),
            );
        }

        return $this->json(['operators' => $byType]);
    }

    private function find(int $id): Position
    {
        $position = $this->positions->findDetail($id);

        if (null === $position) {
            throw $this->createNotFoundException('Позиция не найдена.');
        }

        return $position;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Position $position): array
    {
        $attributes = [];

        foreach ($position->getAttributes() as $link) {
            $attribute = $link->getAttribute();

            $attributes[] = [
                'attributeId' => $attribute->getId(),
                'name'        => $attribute->getName(),
                'category'    => $attribute->getCategory()->value,
                'type'        => $attribute->getType()->value,
                'options'     => $attribute->getOptions(),
                'required'    => $link->isRequired(),
                'section'     => $link->getSection(),
                'sortOrder'   => $link->getSortOrder(),
            ];
        }

        return [
            'id'               => $position->getId(),
            'title'            => $position->getTitle(),
            'shortDescription' => $position->getShortDescription(),
            'company'          => $position->getCompany(),
            'level'            => $position->getLevel(),
            'public'           => $position->isPublic(),
            'maxProjects'      => $position->getMaxProjects(),
            'version'          => $position->getVersion(),
            'createdAt'        => $position->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt'        => $position->getUpdatedAt()->format(\DATE_ATOM),
            'attributes'       => $attributes,
            'accessRules'      => array_map(
                $this->serializeRule(...),
                $position->getAccessRules()->toArray(),
            ),
            'projectTags' => array_map(
                static fn ($tag): string => $tag->getName(),
                $position->getProjectTags()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(PositionAccessRule $rule): array
    {
        return [
            'attributeId' => $rule->getAttribute()->getId(),
            'name'        => $rule->getAttribute()->getName(),
            'type'        => $rule->getType()->value,
            'options'     => $rule->getAttribute()->getOptions(),
            'operator'    => $rule->getOperator()->value,
            'value'       => match ($rule->getType()) {
                AttributeType::Numeric => $rule->getOperandNumber(),
                AttributeType::Boolean => $rule->getOperandBool(),
                AttributeType::Date    => $rule->getOperandDate()?->format('Y-m-d'),
                AttributeType::Select  => FilterOperator::In === $rule->getOperator()
                    ? $rule->getOperandOptions()
                    : $rule->getOperandString(),
                default => $rule->getOperandString(),
            },
        ];
    }
}
