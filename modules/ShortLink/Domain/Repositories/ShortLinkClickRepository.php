<?php

declare(strict_types=1);

namespace Module\ShortLink\Domain\Repositories;

use Module\ShortLink\Domain\Entities\ShortLinkClick;

interface ShortLinkClickRepository
{
    public function save(ShortLinkClick $click, bool $flush = false): void;

    /**
     * Durable click count for a slug — used to rebuild the Redis read model.
     */
    public function countBySlug(string $slug): int;
}
