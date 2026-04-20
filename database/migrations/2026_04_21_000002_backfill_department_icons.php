<?php

declare(strict_types=1);

use App\Domains\Department\ValueObject\DepartmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            DepartmentCategory::Support->value => 'Headphones',
            DepartmentCategory::Registration->value => 'UserPlus',
            DepartmentCategory::Tech->value => 'Wrench',
            DepartmentCategory::Ethics->value => 'Scale',
            DepartmentCategory::Other->value => 'Building2',
        ];

        foreach ($map as $category => $icon) {
            DB::table('departments')->where('category', $category)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        DB::table('departments')->update(['icon' => null]);
    }
};
