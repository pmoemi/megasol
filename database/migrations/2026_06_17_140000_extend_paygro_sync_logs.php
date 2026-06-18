<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paygro_sync_logs', function (Blueprint $table) {
            // Total HTTP/login attempts made during this run.
            $table->unsignedSmallInteger('attempts')->default(1)->after('status');
            // Wall-clock duration of the run in milliseconds.
            $table->unsignedInteger('duration_ms')->nullable()->after('attempts');
            // How the run was triggered: scheduled, manual, or command.
            $table->string('source', 30)->nullable()->after('duration_ms');
            // Whether the session was proactively or reactively refreshed.
            $table->boolean('session_refreshed')->default(false)->after('source');
            // Last HTTP status received from PayGro (useful when run failed).
            $table->unsignedSmallInteger('last_http_status')->nullable()->after('session_refreshed');
        });
    }

    public function down(): void
    {
        Schema::table('paygro_sync_logs', function (Blueprint $table) {
            $table->dropColumn([
                'attempts',
                'duration_ms',
                'source',
                'session_refreshed',
                'last_http_status',
            ]);
        });
    }
};
