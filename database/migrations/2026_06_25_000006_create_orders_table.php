<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
