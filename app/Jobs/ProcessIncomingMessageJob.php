<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Communication\Action\ProcessInboundWebhook;
use App\Application\Integration\ResolveMessengerProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessIncomingMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $sourceId,
        public array $payload,
    ) {}

    public function handle(
        ResolveMessengerProvider $resolveMessenger,
        ProcessInboundWebhook $processInboundWebhook,
    ): void {
        $messenger = $resolveMessenger->run($this->sourceId);
        $processInboundWebhook->run($this->sourceId, $messenger, $this->payload);
    }
}
