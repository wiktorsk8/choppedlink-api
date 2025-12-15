<?php

declare(strict_types=1);

namespace App\ShortLink\Infrastructure\Symfony\Request;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

final readonly class CreateShortLink
{
    public function __construct(
        public string $url,
        public bool $isWhiteListed,
        public ?int $accessLimit,
    ) {
    }
    public static function fromHttp(Request $request): self
    {
        $data = json_decode($request->getContent(), true);

        return new self(
            url: $data['url'] ?? '',
            isWhiteListed: $data['isWhiteListed'] ?? false,
            accessLimit: $data['accessLimit'] ?? null,
        );
    }
    public function toCommand(): \App\ShortLink\Application\Command\CreateShortLink
    {
        return new \App\ShortLink\Application\Command\CreateShortLink(
            id: Uuid::uuid4()->toString(),
            url: $this->url,
            isWhiteListed: $this->isWhiteListed,
            accessLimit: $this->accessLimit
        );
    }
}
