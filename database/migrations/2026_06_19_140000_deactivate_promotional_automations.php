<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotional message types (seasonal, offer, tip, campaign, custom, …) were
 * seeded as active, audience-"all", daily automations — so the unattended
 * runner blasted the whole customer base. Those belong in Campaigns, not
 * automations. Deactivate any automation that isn't a genuinely triggered type;
 * their templates remain available for composing campaigns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automations')) {
            return;
        }

        DB::table('automations')
            ->whereNotIn('type', ['payment_reminder', 'overdue', 'welcome'])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Intentionally irreversible: re-activating promotional auto-blasts is
        // exactly the behaviour we removed.
    }
};
