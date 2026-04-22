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
 * @property string|null $response_sla_text
 * @property string|null $close_tab_notification_text
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
        'response_sla_text',
        'close_tab_notification_text',
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
            'responseSlaText' => 'Стараемся ответить в течение 2 часов в рабочее время.',
            'closeTabNotificationText' => 'Если закроете вкладку: при ответе оператора вы услышите сигнал, увидите число в заголовке страницы и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если оставили email в анкете — дублируем ответ письмом.',
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
        $d = self::defaults();

        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'responseSlaText' => $this->response_sla_text !== null && $this->response_sla_text !== ''
                ? (string) $this->response_sla_text
                : (string) $d['responseSlaText'],
            'closeTabNotificationText' => $this->close_tab_notification_text !== null && $this->close_tab_notification_text !== ''
                ? (string) $this->close_tab_notification_text
                : (string) $d['closeTabNotificationText'],
            'primaryColor' => $this->primary_color,
            'textColor' => $this->text_color,
            'iconSvg' => $this->icon_svg,
        ];
    }
}
