<?php

declare(strict_types=1);

namespace App\Command;

use Module\ShortLink\Domain\Repositories\ShortLinkClickRepository;
use Module\ShortLink\Domain\Repositories\ShortLinkRepository;
use Module\ShortLink\Infrastructure\ReadModel\ShortLinkReadModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Rebuilds the Redis read model from Postgres (the source of truth).
 *
 * Link data is re-projected from the short_links table; the click counter is
 * restored from the durable short_link_click log. Run this after Redis has been
 * lost or flushed — nothing in the read model is authoritative, so it can always
 * be reconstructed here.
 */
#[AsCommand(
    name: 'app:shortlink:rebuild-read-model',
    description: 'Rebuild the Redis read model (links + click counts) from Postgres.',
)]
final class RebuildShortLinkReadModelCommand extends Command
{
    public function __construct(
        private readonly ShortLinkRepository $links,
        private readonly ShortLinkClickRepository $clicks,
        private readonly ShortLinkReadModel $readModel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = 0;
        foreach ($this->links->findAll() as $link) {
            $this->readModel->save(
                id: $link->getId(),
                slug: $link->getSlug(),
                url: $link->getUrl(),
                accessLimit: $link->getAccessLimit(),
                isWhiteListed: $link->getIsWhiteListed(),
            );
            $this->readModel->setClicks($link->getSlug(), $this->clicks->countBySlug($link->getSlug()));
            $count++;
        }

        $io->success(sprintf('Rebuilt read model for %d short link(s).', $count));

        return Command::SUCCESS;
    }
}
