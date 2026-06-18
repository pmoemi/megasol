<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::create('customer_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_list_members', function (Blueprint $table) {
            $table->foreignId('customer_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unique(['customer_list_id', 'customer_id']);
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('rules');
            $table->unsignedInteger('customers_count')->default(0);
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', [
                'payment_reminder',
                'overdue',
                'welcome',
                'seasonal',
                'offer',
                'tip',
                'campaign',
                'custom',
            ]);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', [
                'payment_reminder',
                'overdue',
                'welcome',
                'seasonal',
                'offer',
                'tip',
                'campaign',
                'custom',
            ]);
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('schedule_cron')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('audience_type', ['all', 'segment', 'list', 'payment_status', 'lifecycle']);
            $table->json('audience_meta')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
            $table->enum('audience_type', ['all', 'segment', 'list', 'payment_status', 'lifecycle']);
            $table->json('audience_meta')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('stats')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->text('body');
            $table->enum('status', ['pending', 'queued', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        Schema::create('paygro_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type');
            $table->string('status');
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            $table->foreign('campaign_recipient_id')->references('id')->on('campaign_recipients')->nullOnDelete();
            $table->foreign('automation_id')->references('id')->on('automations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['campaign_recipient_id']);
            $table->dropForeign(['automation_id']);
        });

        Schema::dropIfExists('settings');
        Schema::dropIfExists('paygro_sync_logs');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('customer_list_members');
        Schema::dropIfExists('customer_lists');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
