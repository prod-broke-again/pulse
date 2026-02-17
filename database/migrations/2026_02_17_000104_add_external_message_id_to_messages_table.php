<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_message_id', 255)->nullable()->after('chat_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unique(['chat_id', 'external_message_id'], 'messages_chat_id_external_message_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_chat_id_external_message_id_unique');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('external_message_id');
        });
    }
};
