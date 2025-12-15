<?php

namespace App\User\Infrastructure\Symfony\DTOs;

use App\Shared\Application\Command\Command;
use App\Shared\DTO\DTO;
use App\User\Application\Commands\RegisterUserCommand;
use Symfony\Component\Validator\Constraints as Assert;
use Ramsey\Uuid\Uuid;
class RegisterUserDTO extends DTO
{
    #[Assert\NotBlank(message: 'Email cannot be blank')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Password cannot be blank')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long'
    )]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Password confirmation is required')]
    public ?string $passwordConfirmation = null;

    public function toCommand(): RegisterUserCommand
    {
        return new RegisterUserCommand(
            id: Uuid::uuid4()->toString(),
            email: $this->email,
            password: $this->password,
            passwordConfirmation: $this->passwordConfirmation,
        );
    }
}
