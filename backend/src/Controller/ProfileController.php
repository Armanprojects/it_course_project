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
    #[Route('/{target}', name: 'api_profile_save', requirements: ['target' => 'me|\d+'], methods: ['PATCH'])]
    public function save(
        string $target,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProfileRequest $payload,
    ): JsonResponse {
        $profile = $this->service->save($this->writableProfile($target, $user), $payload);

        return $this->json($this->present($profile));
    }

    #[Route('/{target}/attributes/{attributeId<\d+>}', name: 'api_profile_attribute_add', requirements: ['target' => 'me|\d+'], methods: ['POST'])]
    public function addAttribute(
        string $target,
        int $attributeId,
        Request $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $this->service->addAttribute(
            $this->writableProfile($target, $user),
            $attributeId,
            $this->versionFrom($request),
        );

        return $this->json($this->present($profile));
    }

    #[Route('/{target}/attributes/{attributeId<\d+>}', name: 'api_profile_attribute_remove', requirements: ['target' => 'me|\d+'], methods: ['DELETE'])]
    public function removeAttribute(
        string $target,
        int $attributeId,
        Request $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $this->service->removeAttribute(
            $this->writableProfile($target, $user),
            $attributeId,
            $this->versionFrom($request),
        );

        return $this->json($this->present($profile));
    }

    #[Route('/{target}/projects', name: 'api_profile_project_create', requirements: ['target' => 'me|\d+'], methods: ['POST'])]
    public function createProject(
        string $target,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProjectRequest $payload,
    ): JsonResponse {
        $project = $this->service->createProject($this->writableProfile($target, $user), $payload);

        return $this->json($this->serializer->serializeProject($project), Response::HTTP_CREATED);
    }

    #[Route('/{target}/projects/{id<\d+>}', name: 'api_profile_project_update', requirements: ['target' => 'me|\d+'], methods: ['PUT'])]
    public function updateProject(
        string $target,
        int $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] SaveProjectRequest $payload,
    ): JsonResponse {
        $project = $this->service->updateProject($this->writableProject($target, $id, $user), $payload);

        return $this->json($this->serializer->serializeProject($project));
    }

    #[Route('/{target}/projects/{id<\d+>}', name: 'api_profile_project_delete', requirements: ['target' => 'me|\d+'], methods: ['DELETE'])]
    public function deleteProject(string $target, int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->service->deleteProject($this->writableProject($target, $id, $user));

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

    /**
     * The profile a write targets: "me" for one's own, a numeric id for
     * someone else's — administrators only, since the brief lets them edit any
     * candidate's profile. Routed through one helper so every write endpoint
     * enforces the same rule instead of each repeating it.
     */
    private function writableProfile(string $target, User $user): Profile
    {
        if ('me' === $target) {
            return $this->ownProfile($user);
        }

        $profile = $this->accessibleProfile((int) $target, $user);

        // accessibleProfile() already allows the owner, so an id that happens
        // to be one's own profile behaves exactly like "me".
        if ($profile->getUser() !== $user && !$user->hasRole(UserRole::Admin)) {
            throw $this->createAccessDeniedException('Редактировать профиль может только владелец или администратор.');
        }

        return $profile;
    }

    private function writableProject(string $target, int $id, User $user): Project
    {
        return $this->projectOf($this->writableProfile($target, $user), $id);
    }

    private function projectOf(Profile $profile, int $id): Project
    {
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
