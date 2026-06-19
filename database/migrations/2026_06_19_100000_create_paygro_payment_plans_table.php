<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paygro_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paygro_srl_no')->unique();
            $table->string('plan_name');
            $table->string('product_model')->index();
            $table->decimal('unlock_price', 12, 2)->default(0);
            $table->decimal('down_payment_price', 12, 2)->nullable();
            $table->unsignedInteger('credit_days_down_payment')->nullable();
            $table->decimal('credit_packet_price', 12, 2)->nullable();
            $table->unsignedInteger('credit_packet_size')->nullable();
            $table->unsignedInteger('total_payments')->nullable();
            $table->string('credit_type_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_model', 'plan_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygro_payment_plans');
    }
};
