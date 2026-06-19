<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->foreignId('customer_asset_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('entry_type', 30)->default('installment')->after('customer_asset_id');
            $table->string('source')->nullable()->after('paid_at');
            $table->string('external_reference')->nullable()->after('source');
            $table->string('sales_identifier')->nullable()->after('external_reference');
            $table->string('payment_plan_name')->nullable()->after('sales_identifier');
            $table->json('meta')->nullable()->after('payment_plan_name');

            $table->index(['customer_asset_id', 'due_date']);
            $table->unique(['source', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_reference']);
            $table->dropIndex(['customer_asset_id', 'due_date']);
            $table->dropConstrainedForeignId('customer_asset_id');
            $table->dropColumn([
                'entry_type',
                'source',
                'external_reference',
                'sales_identifier',
                'payment_plan_name',
                'meta',
            ]);
        });
    }
};
