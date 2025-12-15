<?php

namespace App\User\Infrastructure\Doctrine\Queries;

use App\User\Application\Queries\GetUser\DTOs\UserDTO;
use App\User\Infrastructure\Doctrine\Entities\User;
use Doctrine\ORM\EntityManagerInterface;

class GetUserQuery implements \App\User\Application\Queries\GetUser\GetUserQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function execute(string $id): UserDTO
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder->select('u.id', 'u.email')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id);


        /**
         * @var array<int, array{
         *     id: string,
         *     email: string
         * }> $results */
        $results = $queryBuilder->getQuery()->getArrayResult();
        $user = reset($results);
        return new UserDTO(
            id: $user['id'],
            email: $user['email']
        );
    }
}
