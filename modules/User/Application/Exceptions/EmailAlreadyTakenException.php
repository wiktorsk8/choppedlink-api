<?php

namespace App\User\Application\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EmailAlreadyTakenException extends \Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct("This email is already taken", Response::HTTP_CONFLICT, $previous);
    }
}
