<?php

declare(strict_types=1);

namespace App\Application\Integration;

use App\Domains\Integration\Messenger\MessengerProviderFactoryInterface;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;

final readonly class ResolveMessengerProvider
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private MessengerProviderFactoryInterface $messengerFactory,
    ) {}

    public function run(int $sourceId): MessengerProviderInterface
    {
        $source = $this->sourceRepository->findById($sourceId);
        if ($source === null) {
            throw new \InvalidArgumentException("Source not found: {$sourceId}");
        }

        return $this->messengerFactory->forSource($source);
    }
}
