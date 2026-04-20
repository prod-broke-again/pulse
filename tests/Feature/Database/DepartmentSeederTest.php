<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domains\Department\ValueObject\DepartmentCategory;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DepartmentSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const EXPECTED_SLUGS = [
        'achpp-id-account',
        'portal-billing',
        'portal-learning',
        'portal-clubs-events',
        'portal-messenger',
        'aurora-consultations',
        'aurora-realtime',
        'documents-and-apps',
        'ethics-complaints',
        'general',
    ];

    public function test_seeder_creates_ten_departments_with_expected_slugs_for_each_source(): void
    {
        $source = $this->createTestSource();

        $this->seed(DepartmentSeeder::class);

        $slugs = DepartmentModel::query()
            ->where('source_id', $source->id)
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $expected = self::EXPECTED_SLUGS;
        sort($expected);

        $this->assertCount(10, DepartmentModel::query()->where('source_id', $source->id)->get());
        $this->assertSame($expected, $slugs);
    }

    public function test_second_run_does_not_duplicate_departments(): void
    {
        $source = $this->createTestSource();

        $this->seed(DepartmentSeeder::class);
        $firstCount = DepartmentModel::query()->where('source_id', $source->id)->count();

        $this->seed(DepartmentSeeder::class);
        $secondCount = DepartmentModel::query()->where('source_id', $source->id)->count();

        $this->assertSame($firstCount, $secondCount);
        $this->assertSame(10, $secondCount);
    }

    public function test_seeder_does_not_overwrite_existing_ai_enabled_flag(): void
    {
        $source = $this->createTestSource();

        DepartmentModel::query()->create([
            'source_id' => $source->id,
            'name' => 'Прочие (ручная правка)',
            'slug' => 'general',
            'category' => DepartmentCategory::Other,
            'icon' => null,
            'ai_enabled' => false,
            'is_active' => true,
        ]);

        $this->seed(DepartmentSeeder::class);

        $general = DepartmentModel::query()
            ->where('source_id', $source->id)
            ->where('slug', 'general')
            ->firstOrFail();

        $this->assertFalse($general->ai_enabled);
        $this->assertSame('Прочие (ручная правка)', $general->name);
    }

    public function test_seeder_does_not_fail_when_no_sources_exist(): void
    {
        $this->artisan('db:seed', ['--class' => DepartmentSeeder::class])
            ->expectsOutputToContain('Нет источников в БД');

        $this->assertSame(0, DepartmentModel::query()->count());
    }

    private function createTestSource(): SourceModel
    {
        return SourceModel::query()->create([
            'name' => 'Test source',
            'type' => 'web',
            'identifier' => 'test_department_seeder_'.Str::lower(Str::random(12)),
            'secret_key' => null,
            'settings' => [],
        ]);
    }
}
