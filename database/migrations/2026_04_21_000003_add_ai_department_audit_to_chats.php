<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('ai_suggested_department_id')->nullable()
                ->after('department_id')
                ->constrained('departments')->nullOnDelete();
            $table->decimal('ai_department_confidence', 3, 2)->nullable()
                ->after('ai_suggested_department_id');
            $table->timestamp('ai_department_assigned_at')->nullable()
                ->after('ai_department_confidence');
            $table->foreignId('department_reassigned_by_user_id')->nullable()
                ->after('ai_department_assigned_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['ai_suggested_department_id']);
            $table->dropForeign(['department_reassigned_by_user_id']);
            $table->dropColumn([
                'ai_suggested_department_id',
                'ai_department_confidence',
                'ai_department_assigned_at',
                'department_reassigned_by_user_id',
            ]);
        });
    }
};
