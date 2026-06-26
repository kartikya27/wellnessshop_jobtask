<?php

namespace Database\Seeders;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AdPlatform;
use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use App\Models\Courier;
use App\Models\LostCase;
use App\Models\Order;
use App\Models\RtoReason;
use App\Models\Shipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            AiChatMessage::query()->delete();
            AiChatSession::query()->delete();
            LostCase::query()->delete();
            Shipment::query()->delete();
            Order::query()->delete();
            RtoReason::query()->delete();
            Courier::query()->delete();
            CampaignDailyMetric::query()->delete();
            Campaign::query()->delete();
            AdPlatform::query()->delete();

            $this->call([
                AdPlatformSeeder::class,
                CampaignSeeder::class,
                CampaignDailyMetricSeeder::class,
                CourierSeeder::class,
                RtoReasonSeeder::class,
                OrderSeeder::class,
                ShipmentSeeder::class,
                LostCaseSeeder::class,
            ]);
        });
    }
}
