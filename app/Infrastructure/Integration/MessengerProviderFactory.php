<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration;

use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\Messenger\MessengerProviderFactoryInterface;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\ValueObject\SourceType;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Integration\Client\VkApiClient;
use App\Infrastructure\Integration\Messenger\TelegramMessengerProvider;
use App\Infrastructure\Integration\Messenger\VkMessengerProvider;
use App\Infrastructure\Integration\Messenger\WebMessengerProvider;

final class MessengerProviderFactory implements MessengerProviderFactoryInterface
{
    public function forSource(Source $source): MessengerProviderInterface
    {
        return match ($source->type) {
            SourceType::Vk => new VkMessengerProvider(
                new VkApiClient((string) ($source->settings['access_token'] ?? '')),
            ),
            SourceType::Tg => new TelegramMessengerProvider(
                new TelegramApiClient((string) ($source->settings['bot_token'] ?? '')),
            ),
            SourceType::Web => new WebMessengerProvider(),
        };
    }
}
