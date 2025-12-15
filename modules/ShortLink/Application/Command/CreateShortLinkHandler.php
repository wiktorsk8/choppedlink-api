<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Command;

use App\Shared\Application\Command\CommandHandler;
use App\ShortLink\Application\Events\ShortLinkCreated;
use App\ShortLink\Application\Exceptions\CreateShortLinkException;
use App\ShortLink\Application\Exceptions\GenerateSlugException;
use App\ShortLink\Application\Service\LinkShortenerService;
use App\ShortLink\Infrastructure\Cache\Repositories\ShortLinkCacheRepository;
use App\ShortLink\Infrastructure\Doctrine\Entity\ShortLink;
use App\ShortLink\Infrastructure\Doctrine\Repository\ShortLinkRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CreateShortLinkHandler implements CommandHandler
{
    public function __construct(
        protected ShortLinkRepository $repository,
        protected LinkShortenerService $linkShortenerService,
        protected ShortLinkCacheRepository $cacheRepository,
        protected EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CreateShortLinkException
     */
    public function __invoke(CreateShortLink $command): void
    {
        try {
            $slug = $this->linkShortenerService->generateSlug($command->url);
        } catch (GenerateSlugException $e) {
            throw new CreateShortLinkException($e->getMessage(), $e->getCode(), $e);
        }

        $shortLink = new ShortLink(
            id: $command->id,
            slug: $slug,
            url: $command->url,
            accessLimit: $command->accessLimit,
            isWhiteListed: $command->isWhiteListed
        );

        $this->repository->save($shortLink, true);

        $this->eventDispatcher->dispatch(new ShortLinkCreated(
            id: $shortLink->getId(),
            slug: $shortLink->getSlug()
        ));
    }
}
