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
            $table->timestamp('last_activity_at')->nullable()->after('last_auto_reply_at');
            $table->foreignId('previous_chat_id')->nullable()->constrained('chats')->nullOnDelete()->after('last_activity_at');
            $table->unsignedSmallInteger('ai_auto_replies_count')->default(0)->after('previous_chat_id');
            $table->boolean('awaiting_client_feedback')->default(false)->after('ai_auto_replies_count');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_chat_id');
            $table->dropColumn(['last_activity_at', 'ai_auto_replies_count', 'awaiting_client_feedback']);
        });
    }
};
