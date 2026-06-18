<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE campaigns MODIFY audience_type ENUM('all', 'segment', 'list', 'customers', 'payment_status', 'lifecycle') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE campaigns MODIFY audience_type ENUM('all', 'segment', 'list', 'payment_status', 'lifecycle') NOT NULL");
    }
};
