<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('source')->nullable()->after('paid_at');
            $table->json('meta')->nullable()->after('notes');

            $table->unique(['source', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropUnique(['source', 'reference']);
            $table->dropColumn(['source', 'meta']);
        });
    }
};
