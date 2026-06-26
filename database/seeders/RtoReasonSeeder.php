<?php

namespace Database\Seeders;

use App\Models\RtoReason;
use Illuminate\Database\Seeder;

class RtoReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['reason' => 'Customer refused delivery', 'category' => 'Customer', 'is_controllable' => true],
            ['reason' => 'Customer unavailable', 'category' => 'Customer', 'is_controllable' => true],
            ['reason' => 'Incorrect address', 'category' => 'Address', 'is_controllable' => true],
            ['reason' => 'COD not ready', 'category' => 'Payment', 'is_controllable' => true],
            ['reason' => 'Damaged in transit', 'category' => 'Courier', 'is_controllable' => false],
            ['reason' => 'Delivery delayed', 'category' => 'Courier', 'is_controllable' => false],
        ] as $reason) {
            RtoReason::query()->create($reason);
        }
    }
}
