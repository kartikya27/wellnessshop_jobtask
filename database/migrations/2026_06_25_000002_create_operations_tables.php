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
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('service_level');
            $table->decimal('base_cost', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rto_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('reason')->unique();
            $table->string('category')->index();
            $table->boolean('is_controllable')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->date('order_date')->index();
            $table->string('customer_state');
            $table->string('customer_city');
            $table->string('product_category')->index();
            $table->decimal('order_value', 12, 2);
            $table->string('payment_method')->index();
            $table->string('status')->index();
            $table->timestamps();

            $table->index(['order_date', 'status']);
        });

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_cases');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('rto_reasons');
        Schema::dropIfExists('couriers');
    }
};
