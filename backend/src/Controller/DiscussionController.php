<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreatePostRequest;
use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\DiscussionPostRepository;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The discussion tab of a position.
 *
 * Open to every authenticated user — candidates and recruiters both take part
 * — but closed to anonymous visitors, who by the brief may only read the
 * catalogue.
 *
 * Updates reach other viewers by polling: the client passes the last id it
 * holds and gets only what is newer, which keeps a 2-5 second refresh cheap
 * without a socket server to run.
 */
#[Route('/api/positions/{id<\d+>}/discussion')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DiscussionController extends AbstractController
{
    private const PAGE_SIZE = 200;

    public function __construct(
        private readonly PositionRepository $positions,
        private readonly DiscussionPostRepository $posts,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'api_discussion_index', methods: ['GET'])]
    public function index(
        int $id,
        Request $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $position = $this->find($id);
        $after    = $request->query->has('after') ? $request->query->getInt('after') : null;

        $posts = $this->posts->findForPosition($position, $after, self::PAGE_SIZE);

        return $this->json([
            'items' => array_map(
                fn (DiscussionPost $post): array => $this->serialize($post, $user),
                $posts,
            ),
            // The client polls with this, so it is handed back explicitly
            // rather than being derived from a possibly empty list.
            'lastId' => [] === $posts ? $after : end($posts)->getId(),
        ]);
    }

    #[Route('', name: 'api_discussion_create', methods: ['POST'])]
    public function create(
        int $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreatePostRequest $payload,
    ): JsonResponse {
        $post = new DiscussionPost($this->find($id), $user, trim($payload->content));

        $this->em->persist($post);
        $this->em->flush();

        return $this->json($this->serialize($post, $user), Response::HTTP_CREATED);
    }

    private function find(int $id): Position
    {
        $position = $this->positions->find($id);

        if (null === $position) {
            throw $this->createNotFoundException('Позиция не найдена.');
        }

        return $position;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DiscussionPost $post, User $viewer): array
    {
        $author = $post->getAuthor();

        // The brief makes the author's name a link to their profile only for
        // recruiters; for anyone else it stays plain text.
        $canOpenProfile = $viewer->hasRole(UserRole::Recruiter) || $viewer->hasRole(UserRole::Admin);

        return [
            'id'        => $post->getId(),
            'content'   => $post->getContent(),
            'createdAt' => $post->getCreatedAt()->format(\DATE_ATOM),
            'mine'      => $author === $viewer,
            'author'    => [
                'email'     => $author?->getEmail() ?? 'Удалённый пользователь',
                'profileId' => $canOpenProfile ? $author?->getProfile()?->getId() : null,
            ],
        ];
    }
}
