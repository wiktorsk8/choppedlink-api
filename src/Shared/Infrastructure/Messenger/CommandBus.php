<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Messenger;

use App\Shared\Application\Command\Command;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class CommandBus implements \App\Shared\Application\Command\CommandBus
{
    public function __construct(
        private MessageBusInterface $bus
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    public function dispatch(Command $command): void
    {
        try {
            $this->bus->dispatch($command);
        } catch (ExceptionInterface $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
