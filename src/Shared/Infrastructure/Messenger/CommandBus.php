<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use Module\Shared\Application\Command\CommandBus as SharedCommandBus;
use Module\Shared\Application\Command\Command;
class CommandBus implements SharedCommandBus
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
