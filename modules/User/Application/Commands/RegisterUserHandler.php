<?php

namespace App\User\Application\Commands;

use App\Shared\Application\Command\CommandHandler;
use App\User\Application\Exceptions\EmailAlreadyTakenException;
use App\User\Application\Repositories\UserRepository;
use App\User\Infrastructure\Doctrine\Entities\User;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid as SymfonyUuid;
class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        protected UserRepository              $repository,
        protected UserPasswordHasherInterface $userPasswordHasher,
    ){
    }

    /**
     * @throws EmailAlreadyTakenException
     */
    public function __invoke(RegisterUserCommand $command): void
    {
        if (!Uuid::isValid($command->id)) {
            throw new InvalidArgumentException('Invalid UUID format');
        }

        $user = $this->repository->getByEmail($command->email);
        if (null !== $user) {
            throw new EmailAlreadyTakenException();
        }

        $user = new User();
        $user->setEmail($command->email);
        $user->setId(SymfonyUuid::fromString($command->id));

        $hashedPassword = $this->userPasswordHasher->hashPassword($user, $command->password);
        $user->setPassword($hashedPassword);

        $this->repository->save($user);
    }
}
