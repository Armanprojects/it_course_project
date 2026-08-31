<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SaveAttributeRequest;
use App\Entity\Attribute;
use App\Entity\User;
use App\Enum\AttributeCategory;
use App\Enum\AttributeType;
use App\Repository\AttributeRepository;
use App\Service\Profile\AttributeLibraryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The attribute library, as the profile's picker needs it.
 *
 * The brief calls for prefix search, recently-used shortcuts and a category
 * filter, because the library is expected to grow large.
 */
#[Route('/api/attributes')]
final class AttributeController extends AbstractController
{
    private const SEARCH_LIMIT = 50;
    private const RECENT_LIMIT = 8;

    public function __construct(private readonly AttributeRepository $attributes)
    {
    }

    #[Route('', name: 'api_attributes_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $category = AttributeCategory::tryFrom((string) $request->query->get('category'));

        $found = $this->attributes->search(
            $request->query->get('search'),
            $category,
            self::SEARCH_LIMIT,
        );

        $profile = $user->getProfile();

        // "Recently used" only makes sense against a profile: the list is
        // what other people picked lately, minus what this one already has.
        $recent = null === $profile
            ? []
            : $this->attributes->findRecentlyUsed($profile, self::RECENT_LIMIT);

        return $this->json([
            'items'      => array_map($this->serialize(...), $found),
            'recent'     => array_map($this->serialize(...), $recent),
            'categories' => array_map(
                static fn (AttributeCategory $c): string => $c->value,
                AttributeCategory::cases(),
            ),
        ]);
    }

    /**
     * The library as recruiters manage it: includes soft-removed attributes so
     * they can be restored, and reports where each one is in use.
     */
    #[Route('/manage', name: 'api_attributes_manage', methods: ['GET'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function manage(Request $request, AttributeLibraryService $library): JsonResponse
    {
        $category = AttributeCategory::tryFrom((string) $request->query->get('category'));

        $found = $this->attributes->search(
            $request->query->get('search'),
            $category,
            200,
            includeRemoved: true,
        );

        return $this->json([
            'items' => array_map(
                fn (Attribute $attribute): array => $this->serialize($attribute) + [
                    'version' => $attribute->getVersion(),
                    'removed' => $attribute->isRemoved(),
                    'usage'   => $library->usage($attribute),
                ],
                $found,
            ),
            'categories' => array_map(
                static fn (AttributeCategory $c): string => $c->value,
                AttributeCategory::cases(),
            ),
            'types' => array_map(
                static fn (AttributeType $t): string => $t->value,
                AttributeType::cases(),
            ),
        ]);
    }

    #[Route('', name: 'api_attributes_create', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveAttributeRequest $payload,
        AttributeLibraryService $library,
    ): JsonResponse {
        $attribute = $library->create($payload, $user);

        return $this->json($this->serialize($attribute), Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_attributes_update', methods: ['PUT'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function update(
        int $id,
        #[MapRequestPayload] SaveAttributeRequest $payload,
        AttributeLibraryService $library,
    ): JsonResponse {
        $attribute = $library->update($this->find($id), $payload);

        return $this->json($this->serialize($attribute) + ['version' => $attribute->getVersion()]);
    }

    #[Route('/{id<\d+>}', name: 'api_attributes_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function delete(int $id, AttributeLibraryService $library): JsonResponse
    {
        $library->remove($this->find($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id<\d+>}/restore', name: 'api_attributes_restore', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function restore(int $id, AttributeLibraryService $library): JsonResponse
    {
        $attribute = $this->find($id);
        $library->restore($attribute);

        return $this->json($this->serialize($attribute));
    }

    private function find(int $id): Attribute
    {
        $attribute = $this->attributes->find($id);

        if (null === $attribute) {
            throw $this->createNotFoundException('Атрибут не найден.');
        }

        return $attribute;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Attribute $attribute): array
    {
        return [
            'id'          => $attribute->getId(),
            'name'        => $attribute->getName(),
            'description' => $attribute->getDescription(),
            'category'    => $attribute->getCategory()->value,
            'type'        => $attribute->getType()->value,
            'options'     => $attribute->getOptions(),
            'system'      => $attribute->isSystem(),
        ];
    }
}
