<?php

declare(strict_types=1);

namespace App\ShortLink\Infrastructure\Doctrine\Entity;

use App\ShortLink\Infrastructure\Doctrine\Repository\ShortLinkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'short_links')]
#[ORM\Entity(repositoryClass: ShortLinkRepository::class)]

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

    #[ORM\Column(type:  Types::INTEGER, options: ['default' => 0])]
    private int $clickCounter;

    public function __construct(
        string $id,
        string $slug,
        string $url,
        ?int $accessLimit,
        bool $isWhiteListed,
        int $accessCounter = 0,
        int $clickCounter = 0,
    ) {
        $this->id = $id;
        $this->slug = $slug;
        $this->url = $url;
        $this->accessLimit = $accessLimit;
        $this->isWhiteListed = $isWhiteListed;
        $this->accessCounter = $accessCounter;
        $this->clickCounter = $clickCounter;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function hasLimitedAccess(): bool
    {
        return $this->accessLimit !== null;
    }

    public function getAccessCounter(): int
    {
        return $this->accessCounter;
    }

    public function getIsWhiteListed(): bool
    {
        return $this->isWhiteListed;
    }


    public function incrementAccessCounter(): void
    {
        $this->accessCounter++;
    }

    public function incrementClickCounter(): void
    {
        $this->clickCounter++;
    }

    /**
     * @return int|null
     */
    public function getAccessLimit(): ?int
    {
        return $this->accessLimit;
    }
}
