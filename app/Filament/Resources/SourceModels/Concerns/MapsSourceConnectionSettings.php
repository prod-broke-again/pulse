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
        $settings = $data['settings'] ?? [];
        if (! is_array($settings)) {
            $settings = [];
        }

        if (($data['type'] ?? '') !== 'vk') {
            $data['offline_auto_reply_enabled'] = (bool) ($settings['offline_auto_reply_enabled'] ?? false);
            $data['offline_auto_reply_text'] = (string) ($settings['offline_auto_reply_text'] ?? '');
            $conn = $settings;
            unset($conn['offline_auto_reply_enabled'], $conn['offline_auto_reply_text']);
            $data['connection_settings'] = $conn;
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
            $data['settings']['offline_auto_reply_enabled'] = (bool) ($data['offline_auto_reply_enabled'] ?? false);
            $data['settings']['offline_auto_reply_text'] = trim((string) ($data['offline_auto_reply_text'] ?? ''));
            unset($data['offline_auto_reply_enabled'], $data['offline_auto_reply_text']);
        }

        unset($data['connection_settings']);

        return $data;
    }
}
