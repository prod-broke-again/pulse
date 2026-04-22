<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refresh widget copy: no "a few minutes", SLA = within a working day, clarify close-tab + email.
 */
return new class extends Migration
{
    public function up(): void
    {
        $subtitleFrom = 'Обычно отвечаем за пару минут';
        $subtitleTo = 'Напишите — мы ответим в рабочее время';

        $slaFrom = 'Стараемся ответить в течение 2 часов в рабочее время.';
        $slaTo = 'Стараемся ответить в течение рабочего дня.';

        $closeFrom = 'Если закроете вкладку: при ответе оператора вы услышите сигнал, увидите число в заголовке страницы и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если оставили email в анкете — дублируем ответ письмом.';
        $closeTo = 'Пока эта страница открыта, при ответе вы услышите сигнал, увидите число на вкладке и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если вы закроете страницу и не оставили email, переписка не пропадёт: ответ останется в чате — его можно прочитать, снова открыв виджет на этом сайте. С email в анкете дублируем ответ письмом.';

        DB::table('widget_configs')
            ->where('subtitle', $subtitleFrom)
            ->update(['subtitle' => $subtitleTo]);

        DB::table('widget_configs')
            ->where('response_sla_text', $slaFrom)
            ->update(['response_sla_text' => $slaTo]);

        DB::table('widget_configs')
            ->where('close_tab_notification_text', $closeFrom)
            ->update(['close_tab_notification_text' => $closeTo]);
    }

    public function down(): void
    {
        $subtitleFrom = 'Напишите — мы ответим в рабочее время';
        $subtitleTo = 'Обычно отвечаем за пару минут';

        $slaFrom = 'Стараемся ответить в течение рабочего дня.';
        $slaTo = 'Стараемся ответить в течение 2 часов в рабочее время.';

        $closeFrom = 'Пока эта страница открыта, при ответе вы услышите сигнал, увидите число на вкладке и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если вы закроете страницу и не оставили email, переписка не пропадёт: ответ останется в чате — его можно прочитать, снова открыв виджет на этом сайте. С email в анкете дублируем ответ письмом.';
        $closeTo = 'Если закроете вкладку: при ответе оператора вы услышите сигнал, увидите число в заголовке страницы и, при разрешённых уведомлениях браузера, всплывающее уведомление. Если оставили email в анкете — дублируем ответ письмом.';

        DB::table('widget_configs')
            ->where('subtitle', $subtitleFrom)
            ->update(['subtitle' => $subtitleTo]);

        DB::table('widget_configs')
            ->where('response_sla_text', $slaFrom)
            ->update(['response_sla_text' => $slaTo]);

        DB::table('widget_configs')
            ->where('close_tab_notification_text', $closeFrom)
            ->update(['close_tab_notification_text' => $closeTo]);
    }
};
