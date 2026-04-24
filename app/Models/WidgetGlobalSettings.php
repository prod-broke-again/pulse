<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id=1) for globally enabling/disabling the public web widget and fallback copy.
 *
 * @property int $id
 * @property bool $enabled
 * @property string|null $disabled_title
 * @property string|null $disabled_text
 * @property string|null $telegram_url
 * @property string|null $vk_url
 * @property string|null $max_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WidgetGlobalSettings extends Model
{
    public const int DEFAULT_ID = 1;

    public const string DEFAULT_DISABLED_TITLE = 'Сейчас чат на сайте недоступен';

    public const string DEFAULT_DISABLED_TEXT = 'Пожалуйста, напишите нам в удобный мессенджер — мы ответим там.';

    protected $table = 'widget_global_settings';

    protected $fillable = [
        'enabled',
        'disabled_title',
        'disabled_text',
        'telegram_url',
        'vk_url',
        'max_url',
    ];

    /** @return array<string, string|bool> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function singleton(): self
    {
        $row = self::query()->find(self::DEFAULT_ID);
        if ($row === null) {
            $row = new self;
            $row->id = self::DEFAULT_ID;
            $row->enabled = true;
            $row->save();
        }

        return $row;
    }

    /**
     * @return array{
     *     widgetEnabled: bool,
     *     disabledTitle: string,
     *     disabledText: string,
     *     contactLinks: array{telegram: string|null, vk: string|null, max: string|null}
     * }
     */
    public static function publicRuntimeForWidget(): array
    {
        $s = self::singleton();

        $forceEnabled = config('widget.force_enabled');
        $widgetEnabled = $forceEnabled !== null
            ? (bool) $forceEnabled
            : (bool) $s->enabled;

        $title = self::resolveString($s->disabled_title, 'widget.force_disabled_title', self::DEFAULT_DISABLED_TITLE);
        $text = self::resolveString($s->disabled_text, 'widget.force_disabled_text', self::DEFAULT_DISABLED_TEXT);

        return [
            'widgetEnabled' => $widgetEnabled,
            'disabledTitle' => $title,
            'disabledText' => $text,
            'contactLinks' => [
                'telegram' => self::resolveUrl($s->telegram_url, 'widget.force_telegram_url'),
                'vk' => self::resolveUrl($s->vk_url, 'widget.force_vk_url'),
                'max' => self::resolveUrl($s->max_url, 'widget.force_max_url'),
            ],
        ];
    }

    private static function resolveString(?string $dbValue, string $configKey, string $default): string
    {
        $fromEnv = config($configKey);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }
        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        return $default;
    }

    private static function resolveUrl(?string $dbValue, string $configKey): ?string
    {
        $fromEnv = config($configKey);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }
        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        return null;
    }
}
