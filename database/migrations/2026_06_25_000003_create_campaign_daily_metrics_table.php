<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('campaign_daily_metrics');
    }
};
