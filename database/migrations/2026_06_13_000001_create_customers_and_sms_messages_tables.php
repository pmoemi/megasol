<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('product_type')->nullable();
            $table->string('location')->nullable();
            $table->enum('payment_status', ['current', 'due_soon', 'overdue', 'paid_off'])->default('current');
            $table->date('next_payment_date')->nullable();
            $table->decimal('outstanding_balance', 12, 2)->nullable();
            $table->enum('lifecycle_stage', ['new', 'active', 'at_risk', 'loyal', 'inactive'])->default('new');
            $table->timestamp('activated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_status', 'next_payment_date']);
            $table->index(['product_type', 'location']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_recipient_id')->nullable()->index();
            $table->unsignedBigInteger('automation_id')->nullable()->index();
            $table->string('to', 20)->index();
            $table->string('from', 20)->nullable();
            $table->text('body');
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->string('cost', 20)->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('customers');
    }
};
