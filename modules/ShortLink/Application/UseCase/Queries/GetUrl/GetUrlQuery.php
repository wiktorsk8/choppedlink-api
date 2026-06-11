<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Queries\GetUrl;

interface GetUrlQuery
{
    /**
     * Resolve the redirect target for a slug from the read model. Pure read — no mutation.
     * Returns null when the slug is unknown to the read model.
     */
    public function execute(string $slug): ?GetUrlDTO;
}
