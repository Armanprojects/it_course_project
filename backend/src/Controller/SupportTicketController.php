<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateSupportTicketRequest;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\Integration\DropboxClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Тикеты поддержки для Power Automate.
 *
 * Приложение складывает JSON-файл тикета в Dropbox; дальше его подхватывает
 * облачный флоу (триггер «When a file is created») и рассылает уведомления
 * администраторам по адресам из самого файла.
 */
#[Route('/api/support')]
final class SupportTicketController extends AbstractController
{
    public function __construct(
        private readonly DropboxClient $dropbox,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/tickets', name: 'api_support_ticket_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateSupportTicketRequest $payload,
    ): JsonResponse {
        $admins = array_values(array_map(
            static fn (User $admin): string => $admin->getEmail(),
            $this->users->findByRole(UserRole::Admin),
        ));

        $createdAt = new \DateTimeImmutable();

        $ticket = [
            'summary'    => trim($payload->summary),
            'priority'   => $payload->priority,
            'reportedBy' => sprintf('%s (%s)', $user->getEmail(), implode(', ', $this->roleNames($user))),
            'position'   => $payload->position,
            'link'       => $payload->link,
            'admins'     => $admins,
            'createdAt'  => $createdAt->format(\DATE_ATOM),
        ];

        $path = $this->dropbox->upload(
            sprintf('/tickets/ticket-%s-u%d.json', $createdAt->format('Ymd-His'), $user->getId()),
            json_encode($ticket, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
        );

        return $this->json(['path' => $path, 'ticket' => $ticket], Response::HTTP_CREATED);
    }

    /** @return list<string> человекочитаемые роли без технического префикса */
    private function roleNames(User $user): array
    {
        $names = [];

        foreach ($user->getRoles() as $role) {
            if ('ROLE_USER' === $role) {
                continue;
            }

            $names[] = ucfirst(mb_strtolower(str_replace('ROLE_', '', $role)));
        }

        return [] === $names ? ['User'] : $names;
    }
}
