<?php

namespace Database\Seeders;

use App\Models\AdPlatform;
use Illuminate\Database\Seeder;

class AdPlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Meta Ads', 'slug' => 'meta', 'currency' => 'INR', 'is_active' => true],
            ['name' => 'Google Ads', 'slug' => 'google', 'currency' => 'INR', 'is_active' => true],
        ] as $platform) {
            AdPlatform::query()->create($platform);
        }
    }
}
