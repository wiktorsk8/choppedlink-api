<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Queries\GetUrl;

/**
 * Result of resolving a slug against the read model for the redirect path.
 *
 * Carries enough to decide the flow without touching the write model: the target
 * URL plus whether the link enforces an access limit. Only limited links need the
 * synchronous, locked write-model command; unlimited ones redirect straight from here.
 */
final readonly class GetUrlDTO
{
    public function __construct(
        public string $url,
        public bool $isLimited,
    ) {
    }
}
