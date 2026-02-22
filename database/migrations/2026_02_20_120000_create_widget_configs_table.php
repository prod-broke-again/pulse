<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_configs', function (Blueprint $table) {
            $table->id();
            $table->string('source_identifier')->unique();
            $table->string('title')->default('Поддержка');
            $table->string('subtitle')->default('Обычно отвечаем за пару минут');
            $table->string('primary_color', 32)->default('#0ea5e9');
            $table->string('text_color', 32)->default('#ffffff');
            $table->text('icon_svg')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('widget_configs');
    }
};
