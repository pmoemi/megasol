<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->enum('type', ['regular', 'ab_test'])->default('regular')->after('channel');
            $table->unsignedInteger('batch_size')->default(0)->after('sends_per_minute');
            $table->unsignedInteger('batch_delay_seconds')->default(0)->after('batch_size');
            // Email campaigns carry no plain-text body — make it optional so an
            // email-channel campaign can be saved with only body_html.
            $table->text('body')->nullable()->change();
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            // Email recipients have no SMS body — allow NULL.
            $table->text('body')->nullable()->change();
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->index();
            $table->string('ab_variant', 1)->nullable()->after('body_html');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
        });

        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 1);
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->unsignedTinyInteger('percentage')->default(50);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->index(['campaign_id', 'variant']);
        });

        Schema::create('campaign_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->text('original_url');
            $table->string('tracking_hash', 64)->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_links');
        Schema::dropIfExists('ab_test_variants');

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'ab_variant', 'opened_at', 'clicked_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['type', 'batch_size', 'batch_delay_seconds']);
        });
    }
};
