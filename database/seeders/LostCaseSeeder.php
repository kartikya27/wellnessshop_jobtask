<?php

namespace Database\Seeders;

use App\Models\LostCase;
use App\Models\Shipment;
use Illuminate\Database\Seeder;

class LostCaseSeeder extends Seeder
{
    public function run(): void
    {
        $lostShipments = Shipment::query()->with('order')->where('status', 'lost')->get();
        $counter = 301;

        foreach ($lostShipments as $shipment) {
            $claimFiled = fake()->boolean(88);
            $claimAmount = round($shipment->order->order_value + fake()->randomFloat(2, 80, 220), 2);
            $recoveryRate = $claimFiled ? fake()->randomFloat(2, 0.15, 0.9) : 0;

            LostCase::query()->create([
                'shipment_id' => $shipment->id,
                'case_number' => 'LC-' . $counter++,
                'reported_on' => $shipment->shipped_on->copy()->addDays(fake()->numberBetween(3, 9))->toDateString(),
                'status' => fake()->randomElement(['open', 'under_review', 'approved', 'recovered']),
                'claim_filed' => $claimFiled,
                'claim_amount' => $claimAmount,
                'amount_recovered' => round($claimAmount * $recoveryRate, 2),
                'notes' => 'Auto-seeded lost shipment case for dashboard analysis.',
            ]);
        }
    }
}
