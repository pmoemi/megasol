<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Industry/vertical-specific template categories that don't apply to this
     * product (a customer messaging / payments platform). Seeded originally by
     * the premium/enterprise catalogs, now removed.
     *
     * @var array<int, string>
     */
    protected array $irrelevant = [
        'saas', 'ecommerce', 'real_estate', 'agency', 'hospitality',
        'education', 'healthcare', 'nonprofit', 'fitness', 'internal',
    ];

    public function up(): void
    {
        DB::table('email_templates')
            ->whereIn('category', $this->irrelevant)
            ->orWhere('name', 'like', 'SaaS —%')
            ->delete();
    }

    public function down(): void
    {
        // Irreversible: removed seed data is restored by re-running seeders.
    }
};
