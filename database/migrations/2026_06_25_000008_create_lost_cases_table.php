<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('case_number')->unique();
            $table->date('reported_on')->index();
            $table->string('status')->index();
            $table->boolean('claim_filed')->default(false);
            $table->decimal('claim_amount', 12, 2);
            $table->decimal('amount_recovered', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'claim_filed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_cases');
    }
};
