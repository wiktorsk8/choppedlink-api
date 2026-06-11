<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\ReadModel;

use Predis\ClientInterface;

/**
 * Redis-backed read model for short links (the query side of CQRS).
 *
 * Layout:
 *   shortlink:{slug}      Hash  -> { id, slug, url, accessLimit, isWhiteListed, clicks }
 *   shortlink:id:{id}     String-> slug   (secondary index so we can read by id)
 *
 * Everything here is a denormalized projection rebuildable from the write model
 * (Postgres). It is never the source of truth.
 */
final class ShortLinkReadModel
{
    public function __construct(private ClientInterface $redis)
    {
    }

    private function key(string $slug): string
    {
        return "shortlink:$slug";
    }

    private function idIndex(string $id): string
    {
        return "shortlink:id:$id";
    }

    /**
     * Write / refresh the projection for a link. Called by the projector when a
     * ShortLinkCreated event is handled.
     */
    public function save(
        string $id,
        string $slug,
        string $url,
        ?int $accessLimit,
        bool $isWhiteListed,
    ): void {
        $this->redis->hmset($this->key($slug), [
            'id'            => $id,
            'slug'          => $slug,
            'url'           => $url,
            'accessLimit'   => $accessLimit ?? '',
            'isWhiteListed' => $isWhiteListed ? '1' : '0',
        ]);
        // initialise the click counter only if it does not exist yet
        $this->redis->hsetnx($this->key($slug), 'clicks', 0);
        // secondary index: id -> slug
        $this->redis->set($this->idIndex($id), $slug);
    }

    public function incrementClicks(string $slug): void
    {
        $this->redis->hincrby($this->key($slug), 'clicks', 1);
    }

    /**
     * Overwrite the click counter outright. Used when rebuilding the projection
     * from the durable Postgres click log (e.g. after Redis was lost).
     */
    public function setClicks(string $slug, int $clicks): void
    {
        $this->redis->hset($this->key($slug), 'clicks', $clicks);
    }

    /**
     * @return array<string, string>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $data = $this->redis->hgetall($this->key($slug));

        return $data !== [] ? $data : null;
    }

    /**
     * @return array<string, string>|null
     */
    public function findById(string $id): ?array
    {
        $slug = $this->redis->get($this->idIndex($id));
        if ($slug === null) {
            return null;
        }

        return $this->findBySlug($slug);
    }
}
