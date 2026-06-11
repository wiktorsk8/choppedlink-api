<?php

declare(strict_types=1);

namespace App\Tests\Application;

use Module\ShortLink\Application\Exceptions\ShortLinkNotFoundException;
use Module\ShortLink\Application\ReadModel\ClickCounter;
use Module\ShortLink\Application\UseCase\Commands\RegisterShortLinkClick\RegisterShortLinkClick;
use Module\ShortLink\Application\UseCase\Commands\RegisterShortLinkClick\RegisterShortLinkClickHandler;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlDTO;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlQuery;
use Module\ShortLink\Domain\Entities\ShortLink;
use Module\ShortLink\Domain\Entities\ShortLinkClick;
use Module\ShortLink\Domain\Exceptions\CannotAccessUrlException;
use Module\ShortLink\Domain\Repositories\ShortLinkClickRepository;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository;
use PHPUnit\Framework\TestCase;

final class RegisterShortLinkClickHandlerTest extends TestCase
{
    private function getUrlQuery(?GetUrlDTO $return): GetUrlQuery
    {
        return new class($return) implements GetUrlQuery {
            public function __construct(private ?GetUrlDTO $return)
            {
            }

            public function execute(string $slug): ?GetUrlDTO
            {
                return $this->return;
            }
        };
    }

    private function repository(?ShortLink $locked): ShortLinkRepository
    {
        return new class($locked) implements ShortLinkRepository {
            public int $lockCalls = 0;
            public int $saveCalls = 0;

            public function __construct(private ?ShortLink $locked)
            {
            }

            public function findBySlug(string $slug): ?ShortLink
            {
                return $this->locked;
            }

            public function findAll(): array
            {
                return [];
            }

            public function lockBySlug(string $slug): ?ShortLink
            {
                $this->lockCalls++;

                return $this->locked;
            }

            public function save(ShortLink $entity, bool $flush = false): void
            {
                $this->saveCalls++;
            }

            public function remove(ShortLink $entity, bool $flush = false): void
            {
            }
        };
    }

    private function clickRepository(): ShortLinkClickRepository
    {
        return new class implements ShortLinkClickRepository {
            /** @var list<ShortLinkClick> */
            public array $saved = [];

            public function save(ShortLinkClick $click, bool $flush = false): void
            {
                $this->saved[] = $click;
            }

            public function countBySlug(string $slug): int
            {
                return count($this->saved);
            }
        };
    }

    private function clickCounter(): ClickCounter
    {
        return new class implements ClickCounter {
            /** @var list<string> */
            public array $incremented = [];

            public function increment(string $slug): void
            {
                $this->incremented[] = $slug;
            }
        };
    }

    public function testUnlimitedLinkSkipsLockButStillCounts(): void
    {
        $repository = $this->repository(locked: null);
        $clicks = $this->clickRepository();
        $clickCounter = $this->clickCounter();

        $handler = new RegisterShortLinkClickHandler(
            $this->getUrlQuery(new GetUrlDTO(url: 'https://example.com', isLimited: false)),
            $repository,
            $clicks,
            $clickCounter,
        );

        $handler(new RegisterShortLinkClick('slug'));

        self::assertSame(0, $repository->lockCalls, 'unlimited link must not acquire the write lock');
        self::assertSame(0, $repository->saveCalls);
        self::assertCount(1, $clicks->saved);
        self::assertSame(['slug'], $clickCounter->incremented);
    }

    public function testLimitedLinkLocksRecordsAccessAndCounts(): void
    {
        $link = new ShortLink('id', 'slug', 'https://example.com', accessLimit: 5, isWhiteListed: false);
        $repository = $this->repository(locked: $link);
        $clicks = $this->clickRepository();
        $clickCounter = $this->clickCounter();

        $handler = new RegisterShortLinkClickHandler(
            $this->getUrlQuery(new GetUrlDTO(url: 'https://example.com', isLimited: true)),
            $repository,
            $clicks,
            $clickCounter,
        );

        $handler(new RegisterShortLinkClick('slug'));

        self::assertSame(1, $repository->lockCalls);
        self::assertSame(1, $repository->saveCalls);
        self::assertSame(1, $link->getAccessCounter());
        self::assertCount(1, $clicks->saved);
        self::assertSame(['slug'], $clickCounter->incremented);
    }

    public function testLimitReachedThrowsAndCountsNothing(): void
    {
        $link = new ShortLink('id', 'slug', 'https://example.com', accessLimit: 2, isWhiteListed: false, accessCounter: 2);
        $repository = $this->repository(locked: $link);
        $clicks = $this->clickRepository();
        $clickCounter = $this->clickCounter();

        $handler = new RegisterShortLinkClickHandler(
            $this->getUrlQuery(new GetUrlDTO(url: 'https://example.com', isLimited: true)),
            $repository,
            $clicks,
            $clickCounter,
        );

        $this->expectException(CannotAccessUrlException::class);

        try {
            $handler(new RegisterShortLinkClick('slug'));
        } finally {
            self::assertCount(0, $clicks->saved, 'a rejected click must not be logged');
            self::assertSame([], $clickCounter->incremented);
        }
    }

    public function testUnknownSlugThrowsBeforeAnyWrite(): void
    {
        $repository = $this->repository(locked: null);
        $clicks = $this->clickRepository();
        $clickCounter = $this->clickCounter();

        $handler = new RegisterShortLinkClickHandler(
            $this->getUrlQuery(null),
            $repository,
            $clicks,
            $clickCounter,
        );

        $this->expectException(ShortLinkNotFoundException::class);

        try {
            $handler(new RegisterShortLinkClick('slug'));
        } finally {
            self::assertSame(0, $repository->lockCalls);
            self::assertCount(0, $clicks->saved);
            self::assertSame([], $clickCounter->incremented);
        }
    }
}
