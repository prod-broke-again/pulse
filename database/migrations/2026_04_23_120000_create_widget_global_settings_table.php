<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_global_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('disabled_title')->nullable();
            $table->text('disabled_text')->nullable();
            $table->string('telegram_url', 500)->nullable();
            $table->string('vk_url', 500)->nullable();
            $table->string('max_url', 500)->nullable();
            $table->timestamps();
        });

        DB::table('widget_global_settings')->insert([
            'id' => 1,
            'enabled' => true,
            'disabled_title' => 'Сейчас чат на сайте недоступен',
            'disabled_text' => 'Пожалуйста, напишите нам в удобный мессенджер — мы ответим там.',
            'telegram_url' => null,
            'vk_url' => null,
            'max_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_global_settings');
    }
};
