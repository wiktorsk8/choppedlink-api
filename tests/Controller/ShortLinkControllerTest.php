<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Module\ShortLink\Application\Queries\DTOs\GetUrlDTO;
use Module\ShortLink\Application\Queries\GetShortLinkQuery as GetShortLinkQueryInterface;
use Module\ShortLink\Application\Queries\GetUrlQuery as GetUrlQueryClass;
use Module\ShortLink\Application\Queries\Result\ShortLinkDTO;
use Module\ShortLink\Application\Exceptions\GetUrlException;
use Module\Shared\Application\Command\Command;
use Module\Shared\Application\Command\CommandBus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShortLinkControllerTest extends WebTestCase
{
    public function testCreateShortLinkReturnsId(): void
    {
        $client = static::createClient();

        // Stub CommandBus to avoid hitting Messenger
        $commandBusStub = new class() implements CommandBus {
            public function dispatch(Command $command): void
            {
                // no-op in tests
            }
        };

        static::getContainer()->set(CommandBus::class, $commandBusStub);

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

        $dto = new ShortLinkDTO(id: 'abc123', slug: 'my-slug', url: 'https://example.com');

        // Stub the query to return a DTO
        $getShortLinkQueryStub = new class($dto) implements GetShortLinkQueryInterface {
            public function __construct(private ?ShortLinkDTO $return)
            {
            }

            public function execute(string $id): ?ShortLinkDTO
            {
                return $this->return;
            }
        };

        static::getContainer()->set(GetShortLinkQueryInterface::class, $getShortLinkQueryStub);

        $client->request('GET', '/api/short_links/abc123');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($dto->toArray(), $data);
    }

    public function testGetShortLinkNotFound(): void
    {
        $client = static::createClient();

        $getShortLinkQueryStub = new class(null) implements GetShortLinkQueryInterface {
            public function __construct(private ?ShortLinkDTO $return)
            {
            }

            public function execute(string $id): ?ShortLinkDTO
            {
                return $this->return;
            }
        };

        static::getContainer()->set(GetShortLinkQueryInterface::class, $getShortLinkQueryStub);

        $client->request('GET', '/api/short_links/missing');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGetUrlRedirectsWhenFound(): void
    {
        $client = static::createClient();

        // Extend the concrete class to satisfy the controller's type-hint
        $getUrlQueryStub = new class() extends GetUrlQueryClass {
            public function __construct()
            {
                // do not call parent constructor; we're overriding execute
            }

            public function execute(GetUrlDTO $DTO): ?string
            {
                return 'https://example.com/landing';
            }
        };

        static::getContainer()->set(GetUrlQueryClass::class, $getUrlQueryStub);

        $client->request('GET', '/my-slug');

        self::assertResponseStatusCodeSame(302);
        self::assertTrue($client->getResponse()->headers->has('Location'));
        self::assertSame('https://example.com/landing', $client->getResponse()->headers->get('Location'));
    }

    public function testGetUrlBadRequestOnException(): void
    {
        $client = static::createClient();

        $getUrlQueryStub = new class() extends GetUrlQueryClass {
            public function __construct()
            {
            }

            public function execute(GetUrlDTO $DTO): ?string
            {
                throw new GetUrlException('Invalid access');
            }
        };

        static::getContainer()->set(GetUrlQueryClass::class, $getUrlQueryStub);

        $client->request('GET', '/bad-slug');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['message' => 'Invalid access'], $data);
    }

    public function testGetUrlNotFound(): void
    {
        $client = static::createClient();

        $getUrlQueryStub = new class() extends GetUrlQueryClass {
            public function __construct()
            {
            }

            public function execute(GetUrlDTO $DTO): ?string
            {
                return null;
            }
        };

        static::getContainer()->set(GetUrlQueryClass::class, $getUrlQueryStub);

        $client->request('GET', '/missing-slug');

        self::assertResponseStatusCodeSame(404);
    }
}
