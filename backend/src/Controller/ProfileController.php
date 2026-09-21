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

    #[Route('/me', name: 'api_profile_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->present($this->ownProfile($user)));
    }

    #[Route('/{id<\d+>}', name: 'api_profile_show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->present($this->accessibleProfile($id, $user)));
    }

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

    /** @return array<string, mixed> */
    private function present(Profile $profile): array
    {
        return $this->serializer->serialize($profile, $this->attributes->findSystem());
    }

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

        if ($profile->getUser() !== $user && !$user->hasRole(UserRole::Admin)) {
            throw $this->createAccessDeniedException('Профиль доступен только владельцу и администратору.');
        }

        return $profile;
    }

    private function writableProfile(string $target, User $user): Profile
    {
        if ('me' === $target) {
            return $this->ownProfile($user);
        }

        $profile = $this->accessibleProfile((int) $target, $user);

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

        throw $this->createNotFoundException('Проект не найден.');
    }

    private function versionFrom(Request $request): int
    {
        $payload = $request->getPayload();

        return $payload->has('version')
            ? $payload->getInt('version')
            : $request->query->getInt('version');
    }
}
