<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_webhook_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('topic', 64);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_webhook_idempotency_keys');
    }
};
