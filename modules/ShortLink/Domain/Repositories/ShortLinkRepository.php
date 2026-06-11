<?php

namespace Module\ShortLink\Domain\Repositories;

use Module\ShortLink\Domain\Entities\ShortLink;

interface ShortLinkRepository
{
    public function findBySlug(string $slug): ?ShortLink;

    /**
     * @return ShortLink[]
     */
    public function findAll(): array;

    /**
     * Load the aggregate with a pessimistic write lock for safe access accounting.
     */
    public function lockBySlug(string $slug): ?ShortLink;

    public function save(ShortLink $entity, bool $flush = false): void;

    public function remove(ShortLink $entity, bool $flush = false): void;

}