<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SaveProfileRequest;
use App\Dto\SaveProjectRequest;
use App\Entity\Profile;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\AttributeRepository;
use App\Repository\ProfileRepository;
use App\Service\Profile\ProfileSerializer;
use App\Service\Profile\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The personal profile: four sections, all of them private.
 *
 * The brief is explicit that only the owner and an administrator may read or
 * edit a profile — recruiters see candidate data as a rendered CV, never here
 * — so every route runs the same ownership check.
 */
#[Route('/api/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly AttributeRepository $attributes,
        private readonly ProfileSerializer $serializer,
        private readonly ProfileService $service,
    ) {
    }

    /**
     * The signed-in user's own profile.
     */
    #[Route('/me', name: 'api_profile_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->present($this->ownProfile($user)));
    }

    /**
     * Someone else's profile — administrators only, so that an admin can fix a
     * candidate's page "as if they owned it".
     */
    #[Route('/{id<\d+>}', name: 'api_profile_show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->present($this->accessibleProfile($id, $user)));
    }

    /**
     * The autosave endpoint. Returns the whole profile so the client can
     * reconcile against the version the server now holds.
     */
    #[Route('/me', name: 'api_profile_save', methods: ['PATCH'])]
    public function save(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProfileRequest $payload,
    ): JsonResponse {
        $profile = $this->service->save($this->ownProfile($user), $payload);

        return $this->json($this->present($profile));
    }

    #[Route('/me/attributes/{attributeId<\d+>}', name: 'api_profile_attribute_add', methods: ['POST'])]
    public function addAttribute(
        int $attributeId,
        Request $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $this->service->addAttribute(
            $this->ownProfile($user),
            $attributeId,
            $this->versionFrom($request),
        );

        return $this->json($this->present($profile));
    }

    #[Route('/me/attributes/{attributeId<\d+>}', name: 'api_profile_attribute_remove', methods: ['DELETE'])]
    public function removeAttribute(
        int $attributeId,
        Request $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $this->service->removeAttribute(
            $this->ownProfile($user),
            $attributeId,
            $this->versionFrom($request),
        );

        return $this->json($this->present($profile));
    }

    #[Route('/me/projects', name: 'api_profile_project_create', methods: ['POST'])]
    public function createProject(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProjectRequest $payload,
    ): JsonResponse {
        $project = $this->service->createProject($this->ownProfile($user), $payload);

        return $this->json($this->serializer->serializeProject($project), Response::HTTP_CREATED);
    }

    #[Route('/me/projects/{id<\d+>}', name: 'api_profile_project_update', methods: ['PUT'])]
    public function updateProject(
        int $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProjectRequest $payload,
    ): JsonResponse {
        $project = $this->service->updateProject($this->ownProject($id, $user), $payload);

        return $this->json($this->serializer->serializeProject($project));
    }

    #[Route('/me/projects/{id<\d+>}', name: 'api_profile_project_delete', methods: ['DELETE'])]
    public function deleteProject(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->service->deleteProject($this->ownProject($id, $user));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Profile $profile): array
    {
        return $this->serializer->serialize($profile, $this->attributes->findSystem());
    }

    /**
     * Every profile is created alongside its user, but a row can be missing on
     * accounts made before that was true — rebuilding it beats a 500.
     */
    private function ownProfile(User $user): Profile
    {
        $profile = $user->getProfile();

        if (null === $profile) {
            throw $this->createNotFoundException('Профиль не найден.');
        }

        return $this->profiles->findForPage($profile->getId()) ?? $profile;
    }

    private function accessibleProfile(int $id, User $user): Profile
    {
        $profile = $this->profiles->findForPage($id);

        if (null === $profile) {
            throw $this->createNotFoundException('Профиль не найден.');
        }

        // Owner or admin. Recruiters deliberately get 403 here, not a redacted
        // profile: their read-only view of a candidate is the CV page.
        if ($profile->getUser() !== $user && !$user->hasRole(UserRole::Admin)) {
            throw $this->createAccessDeniedException('Профиль доступен только владельцу и администратору.');
        }

        return $profile;
    }

    private function ownProject(int $id, User $user): Project
    {
        $profile = $this->ownProfile($user);

        foreach ($profile->getProjects() as $project) {
            if ($project->getId() === $id) {
                return $project;
            }
        }

        // Looked up through the profile, so someone else's project id is a 404
        // here rather than a 403 — we never confirm that it exists at all.
        throw $this->createNotFoundException('Проект не найден.');
    }

    /**
     * DELETE has no body to map a DTO from, and the version has to travel with
     * every write, so it comes as a query parameter on those routes.
     */
    private function versionFrom(Request $request): int
    {
        $payload = $request->getPayload();

        return $payload->has('version')
            ? $payload->getInt('version')
            : $request->query->getInt('version');
    }
}
