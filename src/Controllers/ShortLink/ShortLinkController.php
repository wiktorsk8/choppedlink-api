<?php

declare(strict_types=1);

namespace App\Controllers\ShortLink;

use App\Requests\ShortLink\CreateShortLink;
use Module\Shared\Application\Command\CommandBus;
use Module\ShortLink\Application\Exceptions\ShortLinkNotFoundException;
use Module\ShortLink\Application\UseCase\Commands\RegisterShortLinkClick\RegisterShortLinkClick;
use Module\ShortLink\Application\UseCase\Queries\GetShortLink\GetShortLinkQuery;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlQuery;
use Module\ShortLink\Domain\Exceptions\CannotAccessUrlException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class ShortLinkController extends AbstractController
{
    /**
     * @throws Throwable
     */
    #[Route('/api/short_links', methods: ['POST'])]
    public function createShortLink(
        Request $request,
        CommandBus $commandBus,
    ): JsonResponse {
        $command = CreateShortLink::fromHttp($request)->toCommand();

        try {
            $commandBus->dispatch($command);
        } catch (\Exception|ExceptionInterface $e) {
            return new JsonResponse([
                "message" => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'id' => $command->id,
        ]);
    }

    #[Route('/api/short_links/{shortLinkId}', methods: ['GET'])]
    public function getShortLink(
        string $shortLinkId,
        GetShortLinkQuery $getShortLinkQuery
    ): JsonResponse {
        $dto = $getShortLinkQuery->execute($shortLinkId);
        if (!$dto) {
            return new JsonResponse([
                'message' => 'Not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($dto->toArray());
    }

    /**
     * @throws Throwable
     */
    #[Route('/{slug}', methods: ['GET'])]
    public function getClickedShortLinkRedirect(
        string $slug,
        CommandBus $commandBus,
        GetUrlQuery $getUrlQuery,
    ): Response {
        try {
            $commandBus->dispatch(new RegisterShortLinkClick($slug));
        } catch (ShortLinkNotFoundException) {
            return new JsonResponse(["message" => "Not found"], Response::HTTP_NOT_FOUND);
        } catch (CannotAccessUrlException $e) {
            return new JsonResponse(["message" => $e->getMessage()], Response::HTTP_GONE);
        }

        $target = $getUrlQuery->execute($slug);
        if ($target === null) {
            return new JsonResponse(["message" => "Not found"], Response::HTTP_NOT_FOUND);
        }

        return new RedirectResponse($target->url);
    }
}
