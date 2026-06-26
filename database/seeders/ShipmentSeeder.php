<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Order;
use App\Models\RtoReason;
use App\Models\Shipment;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = Courier::query()->get()->keyBy('code');
        $speedMap = [
            'SRX' => 52,
            'DLV' => 66,
            'BDP' => 34,
            'ECX' => 58,
        ];
        $rtoReasonIds = RtoReason::query()->pluck('id')->all();

        foreach (Order::query()->with('shipment')->orderBy('order_date')->get() as $order) {
            $courier = fake()->randomElement($couriers->all());
            $isRto = $order->status === 'rto';
            $isLost = $order->status === 'lost';
            $shipDelayHours = fake()->numberBetween(8, 38);
            $transitHours = max(18, (int) round($speedMap[$courier->code] * fake()->randomFloat(2, 0.72, 1.65)));
            $shippedOn = $order->order_date->copy()->addHours($shipDelayHours);
            $expectedDeliveryOn = $shippedOn->copy()->addHours($speedMap[$courier->code] + 22);
            $deliveredOn = $isRto || $isLost ? null : $shippedOn->copy()->addHours($transitHours);
            $rtoOn = $isRto ? $shippedOn->copy()->addHours($transitHours + fake()->numberBetween(30, 96)) : null;

            Shipment::query()->create([
                'order_id' => $order->id,
                'courier_id' => $courier->id,
                'rto_reason_id' => $isRto ? fake()->randomElement($rtoReasonIds) : null,
                'tracking_number' => $courier->code . '-' . fake()->unique()->numerify('########'),
                'shipped_on' => $shippedOn->toDateString(),
                'expected_delivery_on' => $expectedDeliveryOn->toDateString(),
                'delivered_on' => $deliveredOn?->toDateString(),
                'rto_on' => $rtoOn?->toDateString(),
                'status' => $order->status,
                'ship_time_hours' => $shipDelayHours,
                'shipping_cost' => round($courier->base_cost * fake()->randomFloat(2, 0.92, 1.34), 2),
            ]);
        }
    }
}
