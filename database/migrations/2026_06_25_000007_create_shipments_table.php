<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rto_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->unique();
            $table->date('shipped_on')->index();
            $table->date('expected_delivery_on')->index();
            $table->date('delivered_on')->nullable()->index();
            $table->date('rto_on')->nullable()->index();
            $table->string('status')->index();
            $table->unsignedSmallInteger('ship_time_hours');
            $table->decimal('shipping_cost', 10, 2);
            $table->timestamps();

            $table->index(['courier_id', 'shipped_on']);
            $table->index(['status', 'shipped_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
