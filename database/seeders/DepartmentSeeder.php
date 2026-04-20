<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Support\DepartmentIcons;
use Illuminate\Database\Seeder;

final class DepartmentSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, category: string, icon: string, ai_enabled: bool, is_active: bool}> */
    private const DEPARTMENTS = [
        ['name' => 'АЧПП ID: вход и аккаунт', 'slug' => 'achpp-id-account', 'category' => 'registration', 'icon' => 'UserPlus', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: подписки и оплата', 'slug' => 'portal-billing', 'category' => 'support', 'icon' => 'CreditCard', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: курсы и видеотека', 'slug' => 'portal-learning', 'category' => 'support', 'icon' => 'Clipboard', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: клубы и встречи', 'slug' => 'portal-clubs-events', 'category' => 'support', 'icon' => 'Users', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: мессенджер и уведомления', 'slug' => 'portal-messenger', 'category' => 'tech', 'icon' => 'MessageCircle', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Аврора: консультации и расписание', 'slug' => 'aurora-consultations', 'category' => 'support', 'icon' => 'Briefcase', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Аврора: звонки, чат и видео', 'slug' => 'aurora-realtime', 'category' => 'tech', 'icon' => 'Headphones', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Документы и мобильное приложение', 'slug' => 'documents-and-apps', 'category' => 'tech', 'icon' => 'FileText', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Этика и жалобы', 'slug' => 'ethics-complaints', 'category' => 'ethics', 'icon' => 'Scale', 'ai_enabled' => false, 'is_active' => true],
        ['name' => 'Прочие вопросы', 'slug' => 'general', 'category' => 'other', 'icon' => 'HelpCircle', 'ai_enabled' => true, 'is_active' => true],
    ];

    public function run(): void
    {
        $optionalSourceId = $this->resolveOptionalSourceId();

        $sourcesQuery = SourceModel::query()->select(['id', 'name']);
        if ($optionalSourceId !== null) {
            $sourcesQuery->whereKey($optionalSourceId);
        }

        $sources = $sourcesQuery->get();

        if ($sources->isEmpty()) {
            if ($optionalSourceId !== null) {
                $this->command?->warn(sprintf('Источник #%d не найден, сидер пропущен.', $optionalSourceId));
            } else {
                $this->command?->warn('Нет источников в БД, сидер пропущен.');
            }

            return;
        }

        $this->command?->info('Seeding departments:');

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($sources as $source) {
            $created = 0;
            $skipped = 0;

            foreach (self::DEPARTMENTS as $dept) {
                $model = DepartmentModel::firstOrCreate(
                    ['source_id' => $source->id, 'slug' => $dept['slug']],
                    [
                        'name' => $dept['name'],
                        'category' => $dept['category'],
                        'icon' => DepartmentIcons::normalize($dept['icon']),
                        'ai_enabled' => $dept['ai_enabled'],
                        'is_active' => $dept['is_active'],
                    ],
                );

                $model->wasRecentlyCreated ? $created++ : $skipped++;
            }

            $this->command?->info(sprintf(
                '  Source #%d (%s): %d created, %d skipped',
                $source->id,
                $source->name ?? '—',
                $created,
                $skipped
            ));

            $totalCreated += $created;
            $totalSkipped += $skipped;
        }

        $this->command?->info(sprintf(
            'Total: %d created, %d skipped across %d sources.',
            $totalCreated,
            $totalSkipped,
            $sources->count()
        ));
    }

    private function resolveOptionalSourceId(): ?int
    {
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, '--source=')) {
                $raw = substr($arg, strlen('--source='));

                return ctype_digit($raw) ? (int) $raw : null;
            }
        }

        return null;
    }
}
