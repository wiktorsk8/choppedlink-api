<?php

declare(strict_types=1);

namespace Module\ShortLink\Domain\Entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Module\ShortLink\Domain\Exceptions\CannotAccessUrlException;

#[ORM\Table(name: 'short_links')]
#[ORM\Entity]
class ShortLink
{
    #[ORM\Id]
    #[ORM\Column(type:  Types::STRING, unique: true)]
    private string $id;

    #[ORM\Column(type:  Types::STRING, length: 255)]
    private string $slug;

    #[ORM\Column(type:  Types::STRING, length: 255)]
    private string $url;

    #[ORM\Column(type:  Types::INTEGER, options: ['default' => 0])]
    private int $accessCounter;

    #[ORM\Column(type:  Types::INTEGER, nullable: true)]
    private ?int $accessLimit;

    #[ORM\Column(type:  Types::BOOLEAN, options: ['default' => false])]
    private bool $isWhiteListed;

    public function __construct(
        string $id,
        string $slug,
        string $url,
        ?int $accessLimit,
        bool $isWhiteListed,
        int $accessCounter = 0,
    ) {
        $this->id = $id;
        $this->slug = $slug;
        $this->url = $url;
        $this->accessLimit = $accessLimit;
        $this->isWhiteListed = $isWhiteListed;
        $this->accessCounter = $accessCounter;
    }

    /**
     * @throws CannotAccessUrlException when the access limit has been reached
     */
    public function recordAccess(): void
    {
        if ($this->hasLimitedAccess() && $this->accessCounter >= $this->accessLimit) {
            throw new CannotAccessUrlException('URL has reached its access limit');
        }

        $this->accessCounter++;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function hasLimitedAccess(): bool
    {
        return $this->accessLimit !== null;
    }

    public function getAccessCounter(): int
    {
        return $this->accessCounter;
    }

    public function getAccessLimit(): ?int
    {
        return $this->accessLimit;
    }

    public function getIsWhiteListed(): bool
    {
        return $this->isWhiteListed;
    }
}
