<?php

namespace App\Shared\DTO;

use App\Shared\Application\Command\Command;
use Exception;

abstract class DTO
{
    /**
     * @throws Exception
     */
    public function toArray(): array
    {
        throw new Exception('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function toCommand(): Command
    {
        throw new Exception('Not implemented');
    }
}
