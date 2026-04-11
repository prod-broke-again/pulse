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
            $table->index(['chat_id', 'is_read', 'sender_type'], 'messages_chat_id_is_read_sender_type_index');
            $table->index(['chat_id', 'sender_type', 'id'], 'messages_chat_id_sender_type_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_chat_id_is_read_sender_type_index');
            $table->dropIndex('messages_chat_id_sender_type_id_index');
        });
    }
};
