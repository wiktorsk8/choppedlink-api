<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Module\Shared\Application\Command\Command;
use Module\Shared\Application\Command\CommandBus;
use Module\ShortLink\Application\Exceptions\ShortLinkNotFoundException;
use Module\ShortLink\Application\UseCase\Queries\GetShortLink\GetShortLinkQuery;
use Module\ShortLink\Application\UseCase\Queries\GetShortLink\ShortLinkDTO;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlDTO;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlQuery;
use Module\ShortLink\Domain\Exceptions\CannotAccessUrlException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShortLinkControllerTest extends WebTestCase
{
    /**
     * A no-op (or throwing) CommandBus so tests never touch Messenger / the DB.
     */
    private function stubCommandBus(?\Throwable $throw = null): CommandBus
    {
        return new class($throw) implements CommandBus {
            public function __construct(private ?\Throwable $throw)
            {
            }

            public function dispatch(Command $command): void
            {
                if ($this->throw !== null) {
                    throw $this->throw;
                }
            }
        };
    }

    private function stubGetUrlQuery(?GetUrlDTO $return): GetUrlQuery
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

    public function testCreateShortLinkReturnsId(): void
    {
        $client = static::createClient();
        static::getContainer()->set(CommandBus::class, $this->stubCommandBus());

        $client->request(
            'POST',
            '/api/short_links',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'url' => 'https://example.com',
                'isWhiteListed' => true,
                'accessLimit' => 5,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertArrayHasKey('id', $data);
        self::assertNotEmpty($data['id']);
    }

    public function testGetShortLinkFound(): void
    {
        $client = static::createClient();
        $dto = new ShortLinkDTO(id: 'abc123', slug: 'my-slug', url: 'https://example.com', clicks: 7);

        static::getContainer()->set(GetShortLinkQuery::class, new class($dto) implements GetShortLinkQuery {
            public function __construct(private ?ShortLinkDTO $return)
            {
            }

            public function execute(string $id): ?ShortLinkDTO
            {
                return $this->return;
            }
        });

        $client->request('GET', '/api/short_links/abc123');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($dto->toArray(), $data);
    }

    public function testGetShortLinkNotFound(): void
    {
        $client = static::createClient();

        static::getContainer()->set(GetShortLinkQuery::class, new class implements GetShortLinkQuery {
            public function execute(string $id): ?ShortLinkDTO
            {
                return null;
            }
        });

        $client->request('GET', '/api/short_links/missing');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetUrlRedirectsWhenClickRegistered(): void
    {
        $client = static::createClient();
        static::getContainer()->set(CommandBus::class, $this->stubCommandBus());
        static::getContainer()->set(
            GetUrlQuery::class,
            $this->stubGetUrlQuery(new GetUrlDTO(url: 'https://example.com/landing', isLimited: true))
        );

        $client->request('GET', '/my-slug');

        self::assertResponseStatusCodeSame(302);
        self::assertSame('https://example.com/landing', $client->getResponse()->headers->get('Location'));
    }

    public function testGetUrlGoneWhenAccessLimitReached(): void
    {
        $client = static::createClient();
        static::getContainer()->set(
            CommandBus::class,
            $this->stubCommandBus(new CannotAccessUrlException('URL has reached its access limit'))
        );

        $client->request('GET', '/limited-slug');

        self::assertResponseStatusCodeSame(410);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['message' => 'URL has reached its access limit'], $data);
    }

    public function testGetUrlNotFoundWhenCommandRejectsSlug(): void
    {
        $client = static::createClient();
        static::getContainer()->set(CommandBus::class, $this->stubCommandBus(new ShortLinkNotFoundException()));

        $client->request('GET', '/missing-slug');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetUrlNotFoundWhenReadModelEmpty(): void
    {
        $client = static::createClient();
        static::getContainer()->set(CommandBus::class, $this->stubCommandBus());
        static::getContainer()->set(GetUrlQuery::class, $this->stubGetUrlQuery(null));

        $client->request('GET', '/missing-in-readmodel');

        self::assertResponseStatusCodeSame(404);
    }
}
