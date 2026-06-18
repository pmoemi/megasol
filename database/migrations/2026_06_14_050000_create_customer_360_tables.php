<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Customer account-level additions ─────────────────────────────
        Schema::table('customers', function (Blueprint $table) {
            // Lifecycle of the financed account, distinct from payment_status.
            $table->enum('account_status', ['active', 'defaulting', 'written_off', 'paid_off'])
                ->default('active')->after('lifecycle_stage');
            // PAYGO token/credit balance, expressed in days of power remaining.
            $table->integer('token_balance')->default(0)->after('account_status');
            // SMS opt-out (set when a customer texts STOP).
            $table->boolean('sms_opted_out')->default(false)->after('token_balance');
            // Field agent currently responsible for collections on this account.
            $table->foreignId('assigned_agent_id')->nullable()->after('sms_opted_out')
                ->constrained('users')->nullOnDelete();

            $table->index('account_status');
        });

        // ── Payment history (incl. token purchases) ──────────────────────
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['payment', 'token_purchase', 'deposit', 'adjustment', 'refund'])
                ->default('payment');
            $table->string('method')->nullable(); // mpesa, cash, card, bank...
            $table->string('reference')->nullable();
            $table->integer('tokens_credited')->default(0);
            $table->integer('days_credited')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['customer_id', 'paid_at']);
        });

        // ── Token / credit ledger ────────────────────────────────────────
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['credit', 'debit', 'purchase', 'adjustment', 'expiry'])->default('credit');
            $table->integer('tokens'); // signed token units
            $table->integer('days')->default(0); // days of power added/removed
            $table->integer('balance_after')->default(0);
            $table->string('source')->nullable(); // payment, manual, sync
            $table->string('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
        });

        // ── Asset / unit tracking ────────────────────────────────────────
        Schema::create('customer_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('unit_serial')->index();
            $table->string('product_name')->nullable();
            $table->string('model')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->enum('status', ['active', 'faulty', 'repossessed', 'returned', 'decommissioned'])
                ->default('active');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        // ── Repayment schedule ───────────────────────────────────────────
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue', 'waived'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'due_date']);
            $table->index(['customer_id', 'status']);
        });

        // ── Collections: field-agent assignments ─────────────────────────
        Schema::create('agent_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['assigned', 'in_progress', 'promised_to_pay', 'resolved', 'escalated', 'written_off'])
                ->default('assigned');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('amount_at_assignment', 12, 2)->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_assignments');
        Schema::dropIfExists('repayment_schedules');
        Schema::dropIfExists('customer_assets');
        Schema::dropIfExists('token_transactions');
        Schema::dropIfExists('customer_payments');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_agent_id');
            $table->dropColumn(['account_status', 'token_balance', 'sms_opted_out']);
        });
    }
};
