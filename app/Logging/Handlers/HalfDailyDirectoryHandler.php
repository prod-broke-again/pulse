<?php

declare(strict_types=1);

namespace App\Logging\Handlers;

use DateTimeImmutable;
use DateTimeZone;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class HalfDailyDirectoryHandler extends StreamHandler
{
    private string $currentStreamPath = '';

    public function __construct(
        private readonly string $basePath,
        private readonly int $retentionDays = 3,
        int|string|Level $level = Level::Debug,
    ) {
        parent::__construct('php://stderr', $level, true);
    }

    protected function write(LogRecord $record): void
    {
        $path = $this->buildPathForRecord($record);
        if ($path !== $this->currentStreamPath) {
            $this->close();
            $this->url = $path;
            $this->currentStreamPath = $path;
        }

        parent::write($record);
        $this->cleanupOldDayFolders($record->datetime);
    }

    private function buildPathForRecord(LogRecord $record): string
    {
        $dt = DateTimeImmutable::createFromInterface($record->datetime);
        $timezoneName = (string) config('app.timezone', 'UTC');
        $timezone = new DateTimeZone($timezoneName);
        $dt = $dt->setTimezone($timezone);

        $day = $dt->format('Y-m-d');
        $half = ((int) $dt->format('H')) < 12 ? '00-11' : '12-23';
        $directory = rtrim($this->basePath, '/').'/'.$day;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.'/laravel-'.$half.'.log';
    }

    private function cleanupOldDayFolders(\DateTimeInterface $current): void
    {
        $now = DateTimeImmutable::createFromInterface($current)
            ->setTimezone(new DateTimeZone((string) config('app.timezone', 'UTC')));
        $threshold = $now->modify('-'.$this->retentionDays.' days')->setTime(0, 0);

        foreach (glob(rtrim($this->basePath, '/').'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $folderDate = DateTimeImmutable::createFromFormat('Y-m-d', $name);
            if ($folderDate === false || $folderDate >= $threshold) {
                continue;
            }

            foreach (glob($dir.'/*.log') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
        }
    }
}
