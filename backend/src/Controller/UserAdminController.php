<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangeUserRoleRequest;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Admin\UserAdminService;
use App\Service\Auth\UserSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User management: the one thing an administrator can do that nobody else can.
 *
 * Every other admin power in the brief — editing any profile, CV or position,
 * acting as recruiter or candidate — is expressed as an exemption inside the
 * existing endpoints, not as a separate admin API. Only the account list has
 * no counterpart for ordinary users, so it lives here.
 *
 * The whole prefix is already behind ROLE_ADMIN in security.yaml; the
 * attribute below repeats it so the guard travels with the code.
 */
#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserAdminService $service,
        private readonly UserSerializer $serializer,
    ) {
    }

    #[Route('', name: 'api_admin_users_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));

        $found = $this->users->searchForAdmin(
            $request->query->get('search'),
            UserRole::tryFrom((string) $request->query->get('role')),
            UserStatus::tryFrom((string) $request->query->get('status')),
            $page,
            self::PER_PAGE,
        );

        return $this->json([
            'items'   => array_map($this->serializer->serialize(...), $found['items']),
            'total'   => $found['total'],
            'page'    => $page,
            'perPage' => self::PER_PAGE,
        ]);
    }

    #[Route('/{id<\d+>}/block', name: 'api_admin_users_block', methods: ['POST'])]
    public function block(int $id): JsonResponse
    {
        return $this->json($this->serializer->serialize(
            $this->service->block($this->find($id)),
        ));
    }

    #[Route('/{id<\d+>}/block', name: 'api_admin_users_unblock', methods: ['DELETE'])]
    public function unblock(int $id): JsonResponse
    {
        return $this->json($this->serializer->serialize(
            $this->service->unblock($this->find($id)),
        ));
    }

    #[Route('/{id<\d+>}/roles', name: 'api_admin_users_grant_role', methods: ['POST'])]
    public function grantRole(
        int $id,
        #[MapRequestPayload] ChangeUserRoleRequest $payload,
    ): JsonResponse {
        return $this->json($this->serializer->serialize(
            $this->service->grantRole($this->find($id), $payload->roleEnum()),
        ));
    }

    /**
     * Revoking a role. The role is in the body rather than the path because
     * DELETE carries one here for the same reason POST does: it is a value to
     * validate against the enum, not a route segment.
     */
    #[Route('/{id<\d+>}/roles', name: 'api_admin_users_revoke_role', methods: ['DELETE'])]
    public function revokeRole(
        int $id,
        #[MapRequestPayload] ChangeUserRoleRequest $payload,
    ): JsonResponse {
        return $this->json($this->serializer->serialize(
            $this->service->revokeRole($this->find($id), $payload->roleEnum()),
        ));
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->service->delete($this->find($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function find(int $id): User
    {
        $user = $this->users->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('Пользователь не найден.');
        }

        return $user;
    }
}
