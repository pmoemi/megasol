<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Font and card-radius are now per-user (stored on users.preferences). Remove
 * the old global appearance rows so no stale global value leaks onto users who
 * haven't set their own preference — they fall back to the built-in defaults.
 * Brand colors and logo/favicon stay global and are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'theme.font_family',
            'theme.font_weights',
            'theme.font_size',
            'theme.card_radius',
        ])->delete();
    }

    public function down(): void
    {
        // Nothing to restore — these were migrated to per-user preferences.
    }
};
