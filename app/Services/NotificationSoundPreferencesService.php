<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class NotificationSoundPreferencesService
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return config('notification_sounds.defaults', []);
    }

    /**
     * Merge stored JSON with defaults.
     *
     * @return array{mute: bool, volume: float, presets: array<string, string>}
     */
    public function forUser(User $user): array
    {
        $stored = $user->notification_sound_prefs;
        if (! is_array($stored)) {
            $stored = [];
        }

        return $this->merge($stored);
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{mute: bool, volume: float, presets: array<string, string>}
     */
    public function merge(array $stored): array
    {
        $defaults = $this->defaults();
        $presetKeys = ['in_chat', 'in_app', 'background', 'important'];
        $validIds = array_keys(config('notification_sounds.presets', []));

        $presets = [];
        foreach ($presetKeys as $key) {
            $val = $stored['presets'][$key] ?? $defaults['presets'][$key] ?? 'none';
            if (! in_array($val, $validIds, true)) {
                $val = $defaults['presets'][$key] ?? 'none';
            }
            $presets[$key] = $val;
        }

        $mute = isset($stored['mute']) ? (bool) $stored['mute'] : (bool) ($defaults['mute'] ?? false);
        $volume = isset($stored['volume']) ? (float) $stored['volume'] : (float) ($defaults['volume'] ?? 1.0);
        if ($volume < 0) {
            $volume = 0.0;
        }
        if ($volume > 1) {
            $volume = 1.0;
        }

        return [
            'mute' => $mute,
            'volume' => $volume,
            'presets' => $presets,
        ];
    }

    /**
     * @param  array<string, mixed>  $input  Full merged state (current user + request body).
     * @return array{mute: bool, volume: float, presets: array<string, string>}
     */
    public function validateAndNormalize(array $input): array
    {
        $validPresetIds = array_keys(config('notification_sounds.presets', []));

        Validator::make($input, [
            'mute' => ['sometimes', 'boolean'],
            'volume' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'presets' => ['sometimes', 'array'],
            'presets.in_chat' => ['sometimes', Rule::in($validPresetIds)],
            'presets.in_app' => ['sometimes', Rule::in($validPresetIds)],
            'presets.background' => ['sometimes', Rule::in($validPresetIds)],
            'presets.important' => ['sometimes', Rule::in($validPresetIds)],
        ])->validate();

        return $this->merge($input);
    }
}
