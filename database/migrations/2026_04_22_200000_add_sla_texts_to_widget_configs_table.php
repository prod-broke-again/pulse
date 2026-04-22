<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('widget_configs', function (Blueprint $table) {
            $table->text('response_sla_text')->nullable()->after('subtitle');
            $table->text('close_tab_notification_text')->nullable()->after('response_sla_text');
        });
    }

    public function down(): void
    {
        Schema::table('widget_configs', function (Blueprint $table) {
            $table->dropColumn(['response_sla_text', 'close_tab_notification_text']);
        });
    }
};
