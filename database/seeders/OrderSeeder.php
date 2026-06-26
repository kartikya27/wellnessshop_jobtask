<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = Courier::query()->get()->all();
        $states = [
            ['state' => 'Maharashtra', 'city' => 'Mumbai'],
            ['state' => 'Karnataka', 'city' => 'Bengaluru'],
            ['state' => 'Delhi', 'city' => 'New Delhi'],
            ['state' => 'Telangana', 'city' => 'Hyderabad'],
            ['state' => 'Tamil Nadu', 'city' => 'Chennai'],
            ['state' => 'West Bengal', 'city' => 'Kolkata'],
            ['state' => 'Gujarat', 'city' => 'Ahmedabad'],
            ['state' => 'Rajasthan', 'city' => 'Jaipur'],
        ];
        $categories = ['Skincare', 'Haircare', 'Supplements', 'Wellness Kits', 'Personal Care'];
        $orderCounter = 10001;

        foreach (CarbonPeriod::create(CarbonImmutable::today()->subMonths(3)->startOfDay(), CarbonImmutable::yesterday()->startOfDay()) as $date) {
            $dailyOrders = fake()->numberBetween(28, 58) + (in_array($date->dayOfWeekIso, [6, 7], true) ? 14 : 0);

            for ($i = 0; $i < $dailyOrders; $i++) {
                $location = fake()->randomElement($states);
                $courier = fake()->randomElement($couriers);
                $paymentMethod = fake()->randomElement(['prepaid', 'prepaid', 'prepaid', 'cod']);
                $orderValue = fake()->randomFloat(2, 699, 4499);
                $status = fake()->randomElement(['delivered', 'delivered', 'delivered', 'rto', 'lost']);

                Order::query()->create([
                    'order_number' => 'WS-' . $orderCounter++,
                    'order_date' => $date->toDateString(),
                    'customer_state' => $location['state'],
                    'customer_city' => $location['city'],
                    'product_category' => fake()->randomElement($categories),
                    'order_value' => $orderValue,
                    'payment_method' => $paymentMethod,
                    'status' => $status,
                ]);
            }
        }
    }
}
