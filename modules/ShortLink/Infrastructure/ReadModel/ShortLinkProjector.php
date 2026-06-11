<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\ReadModel;

use Module\ShortLink\Domain\Events\ShortLinkCreated;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository;

/**
 * Projects the ShortLinkCreated event from the write model (Postgres) onto the
 * Redis read model. Wired as an event listener in config/services.yaml.
 *
 * Click counting is handled inline by RegisterShortLinkClickHandler, not here.
 */
final class ShortLinkProjector
{
    public function __construct(
        private ShortLinkReadModel  $readModel,
        private ShortLinkRepository $repository,
    ) {
    }

    public function onShortLinkCreated(ShortLinkCreated $event): void
    {
        $link = $this->repository->findBySlug($event->slug);
        if ($link === null) {
            return;
        }

        $this->readModel->save(
            id: $link->getId(),
            slug: $link->getSlug(),
            url: $link->getUrl(),
            accessLimit: $link->getAccessLimit(),
            isWhiteListed: $link->getIsWhiteListed(),
        );
    }
}
