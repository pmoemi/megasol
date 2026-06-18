<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_transactions', function (Blueprint $table) {
            $table->string('external_reference')->nullable()->after('source');
            $table->string('token_value')->nullable()->after('external_reference');
            $table->string('product_serial_number')->nullable()->after('token_value');
            $table->string('token_tag')->nullable()->after('product_serial_number');
            $table->json('meta')->nullable()->after('token_tag');

            $table->unique(['source', 'external_reference']);
            $table->index('product_serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('token_transactions', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_reference']);
            $table->dropIndex(['product_serial_number']);
            $table->dropColumn([
                'external_reference',
                'token_value',
                'product_serial_number',
                'token_tag',
                'meta',
            ]);
        });
    }
};
