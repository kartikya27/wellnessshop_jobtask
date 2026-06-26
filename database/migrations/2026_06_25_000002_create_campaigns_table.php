<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
