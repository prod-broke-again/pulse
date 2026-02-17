<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DemoDataSeeder extends Seeder
{
    private const MESSAGE_SAMPLES = [
        'Здравствуйте! Подскажите, как оформить доставку?',
        'Добрый день. Доставка доступна при заказе от 2000 руб.',
        'Спасибо. А сроки доставки в регионы?',
        'Обычно 3–5 рабочих дней. Точнее подскажем после оформления заказа.',
        'У меня проблема с заказом #8842 — не пришло SMS с трек-номером.',
        'Проверяю ваш заказ, минуту пожалуйста.',
        'Уже нашёл. Трек отправлен в личные сообщения. Получили?',
        'Да, пришло. Спасибо!',
        'Чат передан в отдел техподдержки. Ожидайте, с вами свяжутся.',
        'Когда будете на связи? Нужна консультация по тарифу.',
        'Здравствуйте! Чем могу помочь?',
        'Хочу подключить тариф «Безлимит». Что нужно?',
        'Паспорт и заявление. Можете оформить на сайте или в офисе.',
        'Ок, спасибо.',
        'Добрый вечер. Заказ пришёл с браком, что делать?',
        'Приносим извинения. Напишите номер заказа и опишите брак — оформим замену.',
        'Заказ 9012. Сломана ручка на сумке.',
        'Замену оформили. Новую отправим в течение 2 дней.',
    ];

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@pulse.example'],
            [
                'name' => 'Алексей Админов',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $moderators = [
            User::firstOrCreate(
                ['email' => 'anna@pulse.example'],
                [
                    'name' => 'Анна Модератор',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            ),
            User::firstOrCreate(
                ['email' => 'boris@pulse.example'],
                [
                    'name' => 'Борис Поддержка',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            ),
        ];
        foreach ($moderators as $mod) {
            if (! $mod->hasRole('moderator')) {
                $mod->assignRole('moderator');
            }
        }

        $sources = [
            SourceModel::firstOrCreate(
                ['identifier' => 'vk_support_main'],
                [
                    'name' => 'Поддержка ВКонтакте',
                    'type' => 'vk',
                    'secret_key' => 'demo_secret_vk',
                    'settings' => ['group_id' => 123456],
                ],
            ),
            SourceModel::firstOrCreate(
                ['identifier' => 'tg_support_bot'],
                [
                    'name' => 'Поддержка Telegram',
                    'type' => 'tg',
                    'secret_key' => 'demo_secret_tg',
                    'settings' => ['bot_token' => 'demo'],
                ],
            ),
            SourceModel::firstOrCreate(
                ['identifier' => 'web_site'],
                [
                    'name' => 'Сайт pulse.example',
                    'type' => 'web',
                    'secret_key' => null,
                    'settings' => [],
                ],
            ),
        ];

        $departmentsBySource = [];
        foreach ($sources as $source) {
            $deps = [
                DepartmentModel::firstOrCreate(
                    ['source_id' => $source->id, 'slug' => 'sales'],
                    ['name' => 'Продажи', 'is_active' => true],
                ),
                DepartmentModel::firstOrCreate(
                    ['source_id' => $source->id, 'slug' => 'support'],
                    ['name' => 'Техподдержка', 'is_active' => true],
                ),
            ];
            $departmentsBySource[$source->id] = $deps;
        }

        // Base access by projects/sources.
        // Anna: VK + Web, Boris: Telegram.
        if (isset($moderators[0])) {
            $moderators[0]->sources()->syncWithoutDetaching([
                $sources[0]->id,
                $sources[2]->id,
            ]);
        }
        if (isset($moderators[1])) {
            $moderators[1]->sources()->syncWithoutDetaching([
                $sources[1]->id,
            ]);
        }

        // Optional extra restriction by departments inside assigned projects.
        if (isset($moderators[0], $departmentsBySource[$sources[0]->id][0])) {
            $moderators[0]->departments()->syncWithoutDetaching([
                $departmentsBySource[$sources[0]->id][0]->id, // VK: sales
            ]);
        }
        if (isset($moderators[1], $departmentsBySource[$sources[1]->id][1])) {
            $moderators[1]->departments()->syncWithoutDetaching([
                $departmentsBySource[$sources[1]->id][1]->id, // TG: support
            ]);
        }

        $chatDefs = [
            ['source' => 'vk', 'ext' => 'vk_200100', 'dept' => 'sales', 'status' => 'active', 'assignee' => 0],
            ['source' => 'vk', 'ext' => 'vk_200101', 'dept' => 'support', 'status' => 'new', 'assignee' => null],
            ['source' => 'tg', 'ext' => 'tg_551199', 'dept' => 'sales', 'status' => 'active', 'assignee' => 1],
            ['source' => 'tg', 'ext' => 'tg_551200', 'dept' => 'support', 'status' => 'closed', 'assignee' => 0],
            ['source' => 'web', 'ext' => 'web_anon_8f3a', 'dept' => 'support', 'status' => 'active', 'assignee' => 0],
            ['source' => 'vk', 'ext' => 'vk_200102', 'dept' => 'sales', 'status' => 'new', 'assignee' => null],
            ['source' => 'tg', 'ext' => 'tg_551201', 'dept' => 'support', 'status' => 'active', 'assignee' => 1],
            ['source' => 'web', 'ext' => 'web_guest_a1b2', 'dept' => 'sales', 'status' => 'new', 'assignee' => null],
            ['source' => 'vk', 'ext' => 'vk_200103', 'dept' => 'support', 'status' => 'active', 'assignee' => 0],
            ['source' => 'tg', 'ext' => 'tg_551202', 'dept' => 'sales', 'status' => 'new', 'assignee' => null],
        ];

        $sourceMap = [];
        foreach ($sources as $s) {
            $sourceMap[$s->type] = $s;
        }
        $typeToDeptSlug = ['sales' => 'sales', 'support' => 'support'];

        foreach ($chatDefs as $i => $def) {
            $source = $sourceMap[$def['source']] ?? $sources[0];
            $depts = $departmentsBySource[$source->id] ?? [];
            $dept = collect($depts)->firstWhere('slug', $def['dept']) ?? $depts[0];
            $assigneeId = $def['assignee'] !== null ? $moderators[$def['assignee']]->id : null;

            $chat = ChatModel::firstOrCreate(
                [
                    'source_id' => $source->id,
                    'external_user_id' => $def['ext'],
                ],
                [
                    'department_id' => $dept->id,
                    'user_metadata' => [
                        'name' => 'Клиент ' . ($i + 1),
                        'first_name' => 'Клиент',
                        'last_name' => (string) ($i + 1),
                        'email' => null,
                    ],
                    'status' => $def['status'],
                    'assigned_to' => $assigneeId,
                ],
            );

            $msgCount = MessageModel::where('chat_id', $chat->id)->count();
            if ($msgCount > 0) {
                continue;
            }

            $samples = self::MESSAGE_SAMPLES;
            // Первые 2 чата — больше сообщений (проверка «Load older»)
            $numMessages = $i < 2 ? min(12, count($samples)) : min(5 + ($i % 6), count($samples));
            $used = array_rand(array_flip(range(0, count($samples) - 1)), $numMessages);
            $used = is_array($used) ? $used : [$used];

            foreach ($used as $idx => $key) {
                $text = $samples[$key];
                $isClient = $idx % 3 !== 2;
                MessageModel::create([
                    'chat_id' => $chat->id,
                    'external_message_id' => $def['source'] === 'web' ? null : ($def['ext'] . '_msg_' . ($idx + 1)),
                    'sender_id' => $isClient ? null : $moderators[0]->id,
                    'sender_type' => $isClient ? 'client' : 'moderator',
                    'text' => $text,
                    'payload' => [],
                    'is_read' => $idx < count($used) - 1,
                ]);
            }
        }
    }
}
