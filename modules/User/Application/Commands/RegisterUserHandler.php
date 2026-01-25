<?php

namespace Module\User\Application\Commands;

use Module\Shared\Application\Command\CommandHandler;
use Module\User\Application\Entities\User;
use Module\User\Application\Exceptions\EmailAlreadyTakenException;
use Module\User\Application\Repositories\UserRepository;
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
