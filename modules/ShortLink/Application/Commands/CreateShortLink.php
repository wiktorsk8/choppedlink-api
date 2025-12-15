<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Command;

use App\Shared\Application\Command\Command;

readonly class CreateShortLink implements Command
{
    public function __construct(
        public string $id,
        public string $url,
        public bool $isWhiteListed,
        public ?int $accessLimit,
    ) {
    }
}
