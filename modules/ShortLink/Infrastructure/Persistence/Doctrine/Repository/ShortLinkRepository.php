<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Module\ShortLink\Domain\Entities\ShortLink;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository as ShortLinkRepositoryInterface;

/**
 * @extends ServiceEntityRepository<ShortLink>
 */
class ShortLinkRepository extends ServiceEntityRepository implements ShortLinkRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, ShortLink::class);
    }

    public function findBySlug(string $slug): ?ShortLink
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function lockBySlug(string $slug): ?ShortLink
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function save(ShortLink $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShortLink $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
