<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->json('blocks')->nullable();
            $table->string('category', 50)->default('general');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->enum('channel', ['sms', 'email', 'both'])->default('sms')->after('type');
            $table->string('subject')->nullable()->after('channel');
            $table->longText('body_html')->nullable()->after('body');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->enum('channel', ['sms', 'email'])->default('sms')->after('name');
            $table->string('subject')->nullable()->after('channel');
            $table->longText('body_html')->nullable()->after('body');
            $table->string('preview_text')->nullable()->after('body_html');
            $table->foreignId('email_template_id')->nullable()->after('message_template_id')->constrained('email_templates')->nullOnDelete();
            $table->unsignedSmallInteger('sends_per_minute')->default(60)->after('stats');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('customer_id');
            $table->string('subject')->nullable()->after('email');
            $table->longText('body_html')->nullable()->after('body');
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to');
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->string('status', 30)->default('queued')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', [
                'manual',
                'customer_created',
                'payment_due',
                'payment_overdue',
                'scheduled',
            ])->default('manual');
            $table->string('schedule_cron')->nullable();
            $table->json('definition');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('email_messages');

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['email', 'subject', 'body_html']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['email_template_id']);
            $table->dropColumn(['channel', 'subject', 'body_html', 'preview_text', 'email_template_id', 'sends_per_minute']);
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['channel', 'subject', 'body_html']);
        });

        Schema::dropIfExists('email_templates');
    }
};
