<?php

declare(strict_types=1);

namespace App\Logging;

use App\Logging\Handlers\HalfDailyDirectoryHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

final class SplitDailyLoggerFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $days = max(1, (int) ($config['days'] ?? 3));
        $level = Logger::toMonologLevel($config['level'] ?? Logger::DEBUG);
        $basePath = rtrim((string) ($config['path'] ?? storage_path('logs')), '/');

        $handler = new HalfDailyDirectoryHandler(
            basePath: $basePath,
            retentionDays: $days,
            level: $level,
        );

        $formatter = new LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);

        $logger = new Logger((string) ($config['name'] ?? 'pulse'));
        $logger->pushHandler($handler);

        if (($config['replace_placeholders'] ?? true) === true) {
            $logger->pushProcessor(new PsrLogMessageProcessor);
        }

        return $logger;
    }
}
