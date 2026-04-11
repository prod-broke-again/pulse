<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_user_read_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_user_read_states');
    }
};
