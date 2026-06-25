<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ad_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_platform_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('objective');
            $table->string('status')->index();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->decimal('daily_budget', 12, 2);
            $table->timestamps();

            $table->index(['ad_platform_id', 'status']);
        });

        Schema::create('campaign_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->decimal('spend', 12, 2);
            $table->decimal('revenue', 12, 2);
            $table->unsignedInteger('impressions');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('conversions');
            $table->decimal('average_order_value', 10, 2);
            $table->timestamps();

            $table->unique(['campaign_id', 'metric_date']);
            $table->index('metric_date');
            $table->index(['metric_date', 'campaign_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_daily_metrics');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('ad_platforms');
    }
};
