<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cv;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Service\Cv\CvSerializer;
use App\Service\Cv\CvService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CVs: created by candidates, read by recruiters.
 *
 * Anonymous visitors get nothing here at all — the brief keeps CVs behind
 * authentication even though the position catalogue is open.
 */
#[Route('/api/cvs')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CvController extends AbstractController
{
    public function __construct(
        private readonly CvRepository $cvs,
        private readonly CvSerializer $serializer,
        private readonly CvService $service,
    ) {
    }

    /**
     * Full-text search over submitted CVs — one of the three ways the brief
     * gives recruiters to reach a candidate's data.
     */
    #[Route('/search', name: 'api_cvs_search', methods: ['GET'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function search(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $found = $this->cvs->search((string) $request->query->get('q', ''));

        return $this->json([
            'items' => array_map(
                fn (Cv $cv): array => $this->serializer->serializeRow($cv, $user),
                $found,
            ),
            'total' => \count($found),
        ]);
    }

    /**
     * Starts a CV for a position. Access is re-checked server-side.
     */
    #[Route('/positions/{id<\d+>}', name: 'api_cvs_start', methods: ['POST'])]
    public function start(
        int $id,
        PositionRepository $positions,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $profile = $user->getProfile();

        if (null === $profile) {
            throw $this->createNotFoundException('У пользователя нет профиля.');
        }

        $position = $positions->find($id);

        if (null === $position) {
            throw $this->createNotFoundException('Позиция не найдена.');
        }

        $cv = $this->service->start($profile, $position);

        return $this->json(
            $this->serializer->serialize($this->reload($cv), $user),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id<\d+>}', name: 'api_cvs_show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->find($id);
        $this->assertCanView($cv, $user);

        return $this->json($this->serializer->serialize($cv, $user));
    }

    #[Route('/{id<\d+>}/publish', name: 'api_cvs_publish', methods: ['POST'])]
    public function publish(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->find($id);
        $this->assertCanEdit($cv, $user);

        return $this->json($this->serializer->serialize($this->service->publish($cv), $user));
    }

    #[Route('/{id<\d+>}/publish', name: 'api_cvs_unpublish', methods: ['DELETE'])]
    public function unpublish(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->find($id);
        $this->assertCanEdit($cv, $user);

        return $this->json($this->serializer->serialize($this->service->unpublish($cv), $user));
    }

    #[Route('/{id<\d+>}', name: 'api_cvs_delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->find($id);
        $this->assertCanEdit($cv, $user);

        $this->service->delete($cv);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id<\d+>}/like', name: 'api_cvs_like', methods: ['POST'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function like(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->service->like($this->find($id), $user);

        return $this->json([
            'likesCount' => $cv->getLikesCount(),
            'likedByMe'  => $cv->isLikedBy($user),
        ]);
    }

    #[Route('/{id<\d+>}/like', name: 'api_cvs_unlike', methods: ['DELETE'])]
    #[IsGranted('ROLE_RECRUITER')]
    public function unlike(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $cv = $this->service->unlike($this->find($id), $user);

        return $this->json([
            'likesCount' => $cv->getLikesCount(),
            'likedByMe'  => $cv->isLikedBy($user),
        ]);
    }

    private function find(int $id): Cv
    {
        $cv = $this->cvs->findDetail($id);

        if (null === $cv) {
            throw $this->createNotFoundException('Резюме не найдено.');
        }

        return $cv;
    }

    private function reload(Cv $cv): Cv
    {
        return $this->cvs->findDetail((int) $cv->getId()) ?? $cv;
    }

    /**
     * The owner and admins see any CV of theirs; recruiters see published ones.
     * A draft is the candidate's private work in progress.
     */
    private function assertCanView(Cv $cv, User $user): void
    {
        if ($this->owns($cv, $user) || $user->hasRole(UserRole::Admin)) {
            return;
        }

        if ($user->hasRole(UserRole::Recruiter) && $cv->isPublished()) {
            return;
        }

        throw $this->createAccessDeniedException('Это резюме вам недоступно.');
    }

    /**
     * Recruiters explicitly may not change candidate CVs — administrators may,
     * acting as the owner of any page.
     */
    private function assertCanEdit(Cv $cv, User $user): void
    {
        if (!$this->owns($cv, $user) && !$user->hasRole(UserRole::Admin)) {
            throw $this->createAccessDeniedException('Редактировать резюме может только его владелец.');
        }
    }

    private function owns(Cv $cv, User $user): bool
    {
        return $cv->getCandidate() === $user;
    }
}
