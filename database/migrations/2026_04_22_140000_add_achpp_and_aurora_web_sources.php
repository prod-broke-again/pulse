<?php

declare(strict_types=1);

use App\Domains\Department\ValueObject\DepartmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array SOURCES = [
        [
            'identifier' => 'achpp_web',
            'name' => 'АЧПП (appp-psy.ru)',
        ],
        [
            'identifier' => 'aurora_web',
            'name' => 'Аврора (kukushechka.ru)',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::SOURCES as $src) {
            $existingId = DB::table('sources')->where('identifier', $src['identifier'])->value('id');

            if ($existingId === null) {
                $id = (int) DB::table('sources')->insertGetId([
                    'name' => $src['name'],
                    'type' => 'web',
                    'identifier' => $src['identifier'],
                    'secret_key' => null,
                    'settings' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->ensureDefaultDepartment($id, $now);

                continue;
            }

            $this->ensureDefaultDepartment((int) $existingId, $now);
        }
    }

    private function ensureDefaultDepartment(int $sourceId, \DateTimeInterface $now): void
    {
        $hasActive = DB::table('departments')
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->exists();

        if ($hasActive) {
            return;
        }

        DB::table('departments')->insert([
            'source_id' => $sourceId,
            'name' => 'Поддержка',
            'slug' => 'main',
            'is_active' => true,
            'category' => DepartmentCategory::Support->value,
            'ai_enabled' => true,
            'icon' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $identifiers = array_column(self::SOURCES, 'identifier');

        DB::table('sources')->whereIn('identifier', $identifiers)->delete();
    }
};
