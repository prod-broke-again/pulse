<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Integration\Messenger\MessengerProviderFactoryInterface;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Infrastructure\Integration\MessengerProviderFactory;
use App\Infrastructure\Persistence\EloquentChatRepository;
use App\Infrastructure\Persistence\EloquentDepartmentRepository;
use App\Infrastructure\Persistence\EloquentMessageRepository;
use App\Infrastructure\Persistence\EloquentSourceRepository;
use Illuminate\Support\ServiceProvider;

final class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SourceRepositoryInterface::class, EloquentSourceRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, EloquentDepartmentRepository::class);
        $this->app->bind(ChatRepositoryInterface::class, EloquentChatRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
        $this->app->bind(MessengerProviderFactoryInterface::class, MessengerProviderFactory::class);
    }
}
