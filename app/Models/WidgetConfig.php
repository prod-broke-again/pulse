<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $source_identifier
 * @property string $title
 * @property string $subtitle
 * @property string $primary_color
 * @property string $text_color
 * @property string|null $icon_svg
 */
class WidgetConfig extends Model
{
    public const string CACHE_KEY_PREFIX = 'widget_config_ui:';

    public const int CACHE_TTL_SECONDS = 3600;

    protected $table = 'widget_configs';

    protected $fillable = [
        'source_identifier',
        'title',
        'subtitle',
        'primary_color',
        'text_color',
        'icon_svg',
    ];

    protected $attributes = [
        'title' => 'Поддержка',
        'subtitle' => 'Обычно отвечаем за пару минут',
        'primary_color' => '#0ea5e9',
        'text_color' => '#ffffff',
    ];

    protected static function booted(): void
    {
        static::saved(function (WidgetConfig $config): void {
            Cache::forget(self::CACHE_KEY_PREFIX . $config->source_identifier);
        });

        static::deleted(function (WidgetConfig $config): void {
            Cache::forget(self::CACHE_KEY_PREFIX . $config->source_identifier);
        });
    }

    public static function cacheKey(string $sourceIdentifier): string
    {
        return self::CACHE_KEY_PREFIX . $sourceIdentifier;
    }

    /**
     * Default UI config when no record exists (camelCase for API response).
     *
     * @return array<string, string|null>
     */
    public static function defaults(): array
    {
        return [
            'title' => 'Поддержка',
            'subtitle' => 'Обычно отвечаем за пару минут',
            'primaryColor' => '#0ea5e9',
            'textColor' => '#ffffff',
            'iconSvg' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function toUiArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'primaryColor' => $this->primary_color,
            'textColor' => $this->text_color,
            'iconSvg' => $this->icon_svg,
        ];
    }
}
