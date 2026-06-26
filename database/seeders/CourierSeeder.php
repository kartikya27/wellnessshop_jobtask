<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Shiprocket Express', 'code' => 'SRX', 'service_level' => 'Standard', 'base_cost' => 72, 'is_active' => true],
            ['name' => 'Delhivery Surface', 'code' => 'DLV', 'service_level' => 'Economy', 'base_cost' => 64, 'is_active' => true],
            ['name' => 'Blue Dart Priority', 'code' => 'BDP', 'service_level' => 'Priority', 'base_cost' => 118, 'is_active' => true],
            ['name' => 'Ecom Express', 'code' => 'ECX', 'service_level' => 'Standard', 'base_cost' => 69, 'is_active' => true],
        ] as $courier) {
            Courier::query()->create($courier);
        }
    }
}
