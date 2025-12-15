<?php

declare(strict_types=1);

namespace App\ShortLink\Infrastructure\Doctrine\Queries;

use App\ShortLink\Application\Queries\GetShortLink;
use App\ShortLink\Application\Queries\Result\ShortLinkDTO;
use App\ShortLink\Infrastructure\Doctrine\Entity\ShortLink;
use Doctrine\ORM\EntityManagerInterface;

class GetShortLinkQuery implements GetShortLink
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function execute(string $id): ?ShortLinkDTO
    {
        $builder = $this->entityManager->createQueryBuilder();

        $results = $builder
            ->select('s.id', 's.slug', 's.url')
            ->from(ShortLink::class, 's')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getArrayResult();

        if (empty($results)) {
            return null;
        }

        $result = reset($results);
        return new ShortLinkDTO(
            id: $result['id'],
            slug: $result['slug'],
            url: $result['url']
        );
    }
}
