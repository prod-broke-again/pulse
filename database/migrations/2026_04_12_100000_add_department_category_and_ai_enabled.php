<?php

declare(strict_types=1);

use App\Domains\Department\ValueObject\DepartmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('category', 32)->default(DepartmentCategory::Support->value)->after('slug');
            $table->boolean('ai_enabled')->default(true)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['category', 'ai_enabled']);
        });
    }
};
