<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Session profile for the React admin — roles are UX-only; Symfony security
 * expressions remain the real authorization gate.
 */
final class MeController
{
    #[Route('/api/me', name: 'app_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}