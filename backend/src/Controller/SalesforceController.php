<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SalesforceExportRequest;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\Integration\SalesforceClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Экспорт пользователя в Salesforce: Account + привязанный Contact.
 *
 * Действие доступно самому пользователю в любой роли; администратор может
 * завести в CRM чужую учётку, передав userId.
 */
#[Route('/api/integrations/salesforce')]
final class SalesforceController extends AbstractController
{
    public function __construct(
        private readonly SalesforceClient $salesforce,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/export', name: 'api_salesforce_export', methods: ['POST'])]
    public function export(
        #[CurrentUser] User $user,
        #[MapRequestPayload] SalesforceExportRequest $payload,
    ): JsonResponse {
        $subject = $this->subjectOf($payload, $user);

        $firstName = trim($payload->firstName);
        $lastName  = trim($payload->lastName);
        $company   = $payload->trimmedOrNull($payload->company);
        $phone     = $payload->trimmedOrNull($payload->phone);

        // В Description уходят данные несъёмных полей учётки: кто это в
        // приложении, когда зарегистрирован — плюс комментарий из формы.
        $description = implode("\n", array_filter([
            sprintf('CVMatch user #%d (%s).', $subject->getId(), implode(', ', $subject->getRoles())),
            sprintf('Registered: %s.', $subject->getCreatedAt()->format('Y-m-d')),
            $payload->trimmedOrNull($payload->notes),
        ]));

        $accountId = $this->salesforce->createAccount(array_filter([
            'Name'        => $company ?? sprintf('%s %s', $firstName, $lastName),
            'Phone'       => $phone,
            'Description' => $description,
        ], static fn ($value) => null !== $value));

        $contactId = $this->salesforce->createContact(array_filter([
            'AccountId'   => $accountId,
            'FirstName'   => $firstName,
            'LastName'    => $lastName,
            'Email'       => $subject->getEmail(),
            'Phone'       => $phone,
            'Title'       => $payload->trimmedOrNull($payload->jobTitle),
            'Description' => $description,
        ], static fn ($value) => null !== $value));

        return $this->json([
            'accountId'  => $accountId,
            'contactId'  => $contactId,
            'accountUrl' => $this->salesforce->recordUrl($accountId),
            'contactUrl' => $this->salesforce->recordUrl($contactId),
        ], Response::HTTP_CREATED);
    }

    private function subjectOf(SalesforceExportRequest $payload, User $current): User
    {
        if (null === $payload->userId || $payload->userId === $current->getId()) {
            return $current;
        }

        if (!$current->hasRole(UserRole::Admin)) {
            throw $this->createAccessDeniedException('Экспортировать других пользователей может только администратор.');
        }

        return $this->users->find($payload->userId)
            ?? throw $this->createNotFoundException('Пользователь не найден.');
    }
}
