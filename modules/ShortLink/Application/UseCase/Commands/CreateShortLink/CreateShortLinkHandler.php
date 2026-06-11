<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Commands\CreateShortLink;

use Module\Shared\Application\Command\CommandHandler;
use Module\ShortLink\Application\Exceptions\CreateShortLinkException;
use Module\ShortLink\Application\Services\LinkShortenerService;
use Module\ShortLink\Domain\Entities\ShortLink;
use Module\ShortLink\Domain\Events\ShortLinkCreated;
use Module\ShortLink\Domain\Exceptions\GenerateSlugException;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CreateShortLinkHandler implements CommandHandler
{
    public function __construct(
        protected ShortLinkRepository $repository,
        protected LinkShortenerService $linkShortenerService,
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
