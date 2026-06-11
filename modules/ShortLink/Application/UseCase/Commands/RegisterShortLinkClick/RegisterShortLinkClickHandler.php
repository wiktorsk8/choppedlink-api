<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Commands\RegisterShortLinkClick;

use Module\Shared\Application\Command\CommandHandler;
use Module\ShortLink\Application\Exceptions\ShortLinkNotFoundException;
use Module\ShortLink\Application\ReadModel\ClickCounter;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlQuery;
use Module\ShortLink\Domain\Entities\ShortLinkClick;
use Module\ShortLink\Domain\Exceptions\CannotAccessUrlException;
use Module\ShortLink\Domain\Repositories\ShortLinkClickRepository;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository;
use Ramsey\Uuid\Uuid;

/**
 * Registers a single click on a short link, doing the whole write path in one
 * command. Runs through command.bus, so the doctrine_transaction middleware
 * wraps the Postgres work (the pessimistic lock + counter increment + click row)
 * in one transaction.
 *
 * The limit is resolved from the Redis read model first, so unlimited links skip
 * the pessimistic lock entirely — they only append the durable click row and bump
 * the fast counter.
 */
class RegisterShortLinkClickHandler implements CommandHandler
{
    public function __construct(
        private GetUrlQuery $getUrlQuery,
        private ShortLinkRepository $repository,
        private ShortLinkClickRepository $clicks,
        private ClickCounter $clickCounter,
    ) {
    }

    /**
     * @throws ShortLinkNotFoundException
     * @throws CannotAccessUrlException
     */
    public function __invoke(RegisterShortLinkClick $command): void
    {
        $slug = $command->slug;

        $target = $this->getUrlQuery->execute($slug);
        if ($target === null) {
            throw new ShortLinkNotFoundException();
        }

        if ($target->isLimited) {
            $shortLink = $this->repository->lockBySlug($slug);
            if ($shortLink === null) {
                throw new ShortLinkNotFoundException();
            }

            $shortLink->recordAccess();
            $this->repository->save($shortLink);
        }

        $this->clicks->save(new ShortLinkClick(Uuid::uuid4()->toString(), $slug));
        $this->clickCounter->increment($slug);
    }
}
