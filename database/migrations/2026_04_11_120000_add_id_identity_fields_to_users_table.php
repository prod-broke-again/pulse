<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('id_user_uuid')->nullable()->unique()->after('id');
            $table->string('id_email')->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('name');
            $table->timestamp('id_profile_synced_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['id_user_uuid']);
            $table->dropColumn([
                'id_user_uuid',
                'id_email',
                'avatar_url',
                'id_profile_synced_at',
            ]);
        });
    }
};
