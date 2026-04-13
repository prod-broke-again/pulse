<?php

declare(strict_types=1);

namespace App\Filament\Resources\SourceModels\Concerns;

/**
 * KeyValue cannot share the same state path as {@see Group::statePath('settings')} for VK:
 * both would fight over `settings` and KeyValue's cast leaves the VK fields empty.
 * Non-VK types use `connection_settings` in the form; we sync it to `settings` on persist.
 */
trait MapsSourceConnectionSettings
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapConnectionSettingsBeforeFill(array $data): array
    {
        if (($data['type'] ?? '') !== 'vk') {
            $settings = $data['settings'] ?? [];
            $data['connection_settings'] = is_array($settings) ? $settings : [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapConnectionSettingsBeforePersist(array $data): array
    {
        if (($data['type'] ?? '') !== 'vk') {
            $conn = $data['connection_settings'] ?? [];
            $data['settings'] = is_array($conn) ? $conn : [];
        }

        unset($data['connection_settings']);

        return $data;
    }
}
