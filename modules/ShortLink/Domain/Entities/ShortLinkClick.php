<?php

declare(strict_types=1);

namespace Module\ShortLink\Domain\Entities;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only record of a single access to a short link. One row per access.
 *
 * This is the durable source of truth for click statistics: the Redis counter
 * is a fast projection of these rows and can always be rebuilt by counting them
 * (see ShortLinkClickRepository::countBySlug).
 */
#[ORM\Table(name: 'short_link_click')]
#[ORM\Index(name: 'idx_short_link_click_slug', columns: ['slug'])]
#[ORM\Entity]
class ShortLinkClick
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $slug;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(string $id, string $slug, ?DateTimeImmutable $createdAt = null)
    {
        $this->id = $id;
        $this->slug = $slug;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
