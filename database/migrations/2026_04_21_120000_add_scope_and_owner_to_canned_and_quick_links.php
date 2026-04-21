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
        Schema::table('canned_responses', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('scope_type', 32)->nullable()->after('owner_user_id');
            $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type');
            $table->index(['scope_type', 'scope_id']);
            $table->index(['owner_user_id']);
        });

        DB::table('canned_responses')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                /** @var object{id:int,source_id:int|null} $row */
                if ($row->source_id !== null) {
                    DB::table('canned_responses')->where('id', $row->id)->update([
                        'scope_type' => 'source',
                        'scope_id' => $row->source_id,
                    ]);
                }
            }
        });

        Schema::table('canned_responses', function (Blueprint $table): void {
            $table->dropForeign(['source_id']);
            $table->dropColumn('source_id');
        });

        Schema::table('quick_links', function (Blueprint $table): void {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('scope_type', 32)->nullable()->after('owner_user_id');
            $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type');
            $table->index(['scope_type', 'scope_id']);
            $table->index(['owner_user_id']);
        });

        DB::table('quick_links')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                /** @var object{id:int,source_id:int|null} $row */
                if ($row->source_id !== null) {
                    DB::table('quick_links')->where('id', $row->id)->update([
                        'scope_type' => 'source',
                        'scope_id' => $row->source_id,
                    ]);
                }
            }
        });

        Schema::table('quick_links', function (Blueprint $table): void {
            $table->dropIndex(['source_id', 'is_active', 'sort_order']);
            $table->dropForeign(['source_id']);
            $table->dropColumn('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('canned_responses', function (Blueprint $table): void {
            $table->foreignId('source_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('canned_responses')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                /** @var object{id:int,scope_type:string|null,scope_id:int|null} $row */
                if ($row->scope_type === 'source' && $row->scope_id !== null) {
                    DB::table('canned_responses')->where('id', $row->id)->update([
                        'source_id' => $row->scope_id,
                    ]);
                }
            }
        });

        Schema::table('canned_responses', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['scope_type', 'scope_id']);
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn(['owner_user_id', 'scope_type', 'scope_id']);
        });

        Schema::table('quick_links', function (Blueprint $table): void {
            $table->foreignId('source_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['source_id', 'is_active', 'sort_order']);
        });

        DB::table('quick_links')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                /** @var object{id:int,scope_type:string|null,scope_id:int|null} $row */
                if ($row->scope_type === 'source' && $row->scope_id !== null) {
                    DB::table('quick_links')->where('id', $row->id)->update([
                        'source_id' => $row->scope_id,
                    ]);
                }
            }
        });

        Schema::table('quick_links', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex(['scope_type', 'scope_id']);
            $table->dropIndex(['owner_user_id']);
            $table->dropColumn(['owner_user_id', 'scope_type', 'scope_id']);
        });
    }
};
