<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Shared\Infrastructure\Symfony\Messenger\CommandBus;
use App\User\Application\Exceptions\EmailAlreadyTakenException;
use App\User\Application\Queries\GetUser\GetUserQuery;
use App\User\Infrastructure\Symfony\DTOs\RegisterUserDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class RegisterController extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Route('/api/users/register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterUserDTO $DTO,
        CommandBus $commandBus,
        GetUserQuery $getUserQuery
    ): JsonResponse {

        $command = $DTO->toCommand();
        try {
            $commandBus->dispatch($command);
        } catch (EmailAlreadyTakenException $e) {
            return new JsonResponse([
                "message" => $e->getMessage(),
            ], $e->getCode());
        }

        $userDTO = $getUserQuery->execute($command->id);

        return new JsonResponse(
            $userDTO->toArray()
        );
    }
}
