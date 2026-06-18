<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The curated core email templates (matching the parent project). All
     * other seeded catalog templates are removed to keep the gallery lean.
     *
     * @var array<int, string>
     */
    protected array $keep = [
        'Welcome Email',
        'Newsletter',
        'Product Announcement',
        'Flash Sale',
        'Abandoned Cart',
        'Thank You',
        'Feedback Request',
        'Re-engagement',
        'Event Invitation',
        'Monthly Report',
        'Feature Update',
        'Referral Program',
    ];

    public function up(): void
    {
        DB::table('email_templates')
            ->whereNotIn('name', $this->keep)
            ->delete();
    }

    public function down(): void
    {
        // Irreversible: removed templates are restored by re-running seeders.
    }
};
