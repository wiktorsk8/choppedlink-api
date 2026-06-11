<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\ShortLink\Domain\Entities\ShortLinkClick;
use Module\ShortLink\Domain\Repositories\ShortLinkClickRepository as ShortLinkClickRepositoryInterface;

/**
 * @extends ServiceEntityRepository<ShortLinkClick>
 */
class ShortLinkClickRepository extends ServiceEntityRepository implements ShortLinkClickRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShortLinkClick::class);
    }

    public function save(ShortLinkClick $click, bool $flush = false): void
    {
        $this->getEntityManager()->persist($click);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countBySlug(string $slug): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
