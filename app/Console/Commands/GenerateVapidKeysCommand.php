<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

final class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'web-push:vapid';

    protected $description = 'Generate VAPID keys for Web Push (add to .env: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('Add these to your .env file:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $host = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
        $this->line('VAPID_SUBJECT=mailto:admin@'.$host);
        $this->newLine();

        return self::SUCCESS;
    }
}
