<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardMetricsSeeder extends Seeder
{
    /**
     * Seed three months of realistic D2C marketing and operations data.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->clearDashboardTables();

            $platformIds = $this->seedAdPlatforms();
            $campaignIds = $this->seedCampaigns($platformIds);
            $this->seedCampaignDailyMetrics($campaignIds);

            $courierIds = $this->seedCouriers();
            $rtoReasonIds = $this->seedRtoReasons();
            $this->seedOrdersAndShipments($courierIds, $rtoReasonIds);
        });
    }

    private function clearDashboardTables(): void
    {
        DB::table('lost_cases')->delete();
        DB::table('shipments')->delete();
        DB::table('orders')->delete();
        DB::table('rto_reasons')->delete();
        DB::table('couriers')->delete();
        DB::table('campaign_daily_metrics')->delete();
        DB::table('campaigns')->delete();
        DB::table('ad_platforms')->delete();
    }

    /**
     * @return array<string, int>
     */
    private function seedAdPlatforms(): array
    {
        $now = now();
        $platforms = [
            ['name' => 'Meta Ads', 'slug' => 'meta', 'currency' => 'INR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Google Ads', 'slug' => 'google', 'currency' => 'INR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('ad_platforms')->insert($platforms);

        return DB::table('ad_platforms')->pluck('id', 'slug')->all();
    }

    /**
     * @param array<string, int> $platformIds
     * @return array<int, array<string, mixed>>
     */
    private function seedCampaigns(array $platformIds): array
    {
        $now = now();
        $campaigns = [
            ['platform' => 'meta', 'name' => 'Meta Prospecting - Skincare Starters', 'objective' => 'Conversions', 'status' => 'active', 'daily_budget' => 18500],
            ['platform' => 'meta', 'name' => 'Meta Retargeting - Cart Recovery', 'objective' => 'Conversions', 'status' => 'active', 'daily_budget' => 8200],
            ['platform' => 'meta', 'name' => 'Meta Creative Test - Wellness Bundles', 'objective' => 'Creative Testing', 'status' => 'paused', 'daily_budget' => 5200],
            ['platform' => 'google', 'name' => 'Google Search - Brand Defense', 'objective' => 'Search Sales', 'status' => 'active', 'daily_budget' => 9500],
            ['platform' => 'google', 'name' => 'Google Shopping - Best Sellers', 'objective' => 'Shopping Sales', 'status' => 'active', 'daily_budget' => 14200],
            ['platform' => 'google', 'name' => 'Google PMax - New Customer Scale', 'objective' => 'New Customers', 'status' => 'learning', 'daily_budget' => 12000],
        ];

        foreach ($campaigns as $campaign) {
            $id = DB::table('campaigns')->insertGetId([
                'ad_platform_id' => $platformIds[$campaign['platform']],
                'name' => $campaign['name'],
                'objective' => $campaign['objective'],
                'status' => $campaign['status'],
                'started_on' => CarbonImmutable::today()->subMonths(3)->toDateString(),
                'ended_on' => null,
                'daily_budget' => $campaign['daily_budget'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $campaignIds[] = [
                'id' => $id,
                'platform' => $campaign['platform'],
                'status' => $campaign['status'],
                'daily_budget' => $campaign['daily_budget'],
            ];
        }

        return $campaignIds ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $campaigns
     */
    private function seedCampaignDailyMetrics(array $campaigns): void
    {
        $start = CarbonImmutable::today()->subMonths(3)->startOfDay();
        $end = CarbonImmutable::yesterday()->startOfDay();
        $now = now();
        $rows = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dayIndex = $start->diffInDays($date);
            $weekdayMultiplier = in_array($date->dayOfWeekIso, [6, 7], true) ? 1.18 : 1.0;
            $seasonality = 1 + (sin($dayIndex / 8) * 0.08);

            foreach ($campaigns as $campaign) {
                $statusMultiplier = $campaign['status'] === 'paused' ? 0.35 : 1.0;
                $platformMultiplier = $campaign['platform'] === 'google' ? 0.92 : 1.06;
                $spend = round($campaign['daily_budget'] * $weekdayMultiplier * $seasonality * $statusMultiplier * $platformMultiplier * fake()->randomFloat(2, 0.84, 1.13), 2);
                $cpm = $campaign['platform'] === 'meta' ? fake()->randomFloat(2, 145, 235) : fake()->randomFloat(2, 95, 180);
                $ctr = $campaign['platform'] === 'meta' ? fake()->randomFloat(4, 0.0095, 0.021) : fake()->randomFloat(4, 0.025, 0.055);
                $impressions = max(1000, (int) round(($spend / $cpm) * 1000));
                $clicks = max(20, (int) round($impressions * $ctr));
                $aov = fake()->randomFloat(2, 1320, 2650);
                $roasBase = $campaign['platform'] === 'google' ? fake()->randomFloat(2, 2.25, 4.85) : fake()->randomFloat(2, 1.75, 4.25);
                $revenue = round($spend * $roasBase, 2);
                $conversions = max(1, (int) round($revenue / $aov));

                $rows[] = [
                    'campaign_id' => $campaign['id'],
                    'metric_date' => $date->toDateString(),
                    'spend' => $spend,
                    'revenue' => $revenue,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'average_order_value' => $aov,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('campaign_daily_metrics')->insert($chunk);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seedCouriers(): array
    {
        $now = now();
        $couriers = [
            ['name' => 'Shiprocket Express', 'code' => 'SRX', 'service_level' => 'Standard', 'base_cost' => 72, 'risk' => 0.08, 'speed' => 52],
            ['name' => 'Delhivery Surface', 'code' => 'DLV', 'service_level' => 'Economy', 'base_cost' => 64, 'risk' => 0.13, 'speed' => 66],
            ['name' => 'Blue Dart Priority', 'code' => 'BDP', 'service_level' => 'Priority', 'base_cost' => 118, 'risk' => 0.045, 'speed' => 34],
            ['name' => 'Ecom Express', 'code' => 'ECX', 'service_level' => 'Standard', 'base_cost' => 69, 'risk' => 0.11, 'speed' => 58],
        ];

        foreach ($couriers as $courier) {
            $id = DB::table('couriers')->insertGetId([
                'name' => $courier['name'],
                'code' => $courier['code'],
                'service_level' => $courier['service_level'],
                'base_cost' => $courier['base_cost'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $seeded[] = [...$courier, 'id' => $id];
        }

        return $seeded ?? [];
    }

    /**
     * @return array<string, int>
     */
    private function seedRtoReasons(): array
    {
        $now = now();
        $reasons = [
            ['reason' => 'Customer refused delivery', 'category' => 'Customer', 'is_controllable' => true],
            ['reason' => 'Customer unavailable', 'category' => 'Customer', 'is_controllable' => true],
            ['reason' => 'Incorrect address', 'category' => 'Address', 'is_controllable' => true],
            ['reason' => 'COD not ready', 'category' => 'Payment', 'is_controllable' => true],
            ['reason' => 'Damaged in transit', 'category' => 'Courier', 'is_controllable' => false],
            ['reason' => 'Delivery delayed', 'category' => 'Courier', 'is_controllable' => false],
        ];

        foreach ($reasons as $reason) {
            DB::table('rto_reasons')->insert([
                ...$reason,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return DB::table('rto_reasons')->pluck('id', 'reason')->all();
    }

    /**
     * @param array<int, array<string, mixed>> $couriers
     * @param array<string, int> $rtoReasonIds
     */
    private function seedOrdersAndShipments(array $couriers, array $rtoReasonIds): void
    {
        $start = CarbonImmutable::today()->subMonths(3)->startOfDay();
        $end = CarbonImmutable::yesterday()->startOfDay();
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
        $reasonNames = array_keys($rtoReasonIds);
        $now = now();
        $orderCounter = 10001;
        $lostCounter = 301;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dailyOrders = fake()->numberBetween(28, 58) + (in_array($date->dayOfWeekIso, [6, 7], true) ? 14 : 0);

            for ($i = 0; $i < $dailyOrders; $i++) {
                $location = fake()->randomElement($states);
                $courier = fake()->randomElement($couriers);
                $paymentMethod = fake()->randomElement(['prepaid', 'prepaid', 'prepaid', 'cod']);
                $orderValue = fake()->randomFloat(2, 699, 4499);
                $isRto = fake()->boolean((int) round($courier['risk'] * ($paymentMethod === 'cod' ? 135 : 85)));
                $isLost = ! $isRto && fake()->boolean($courier['code'] === 'DLV' ? 2 : 1);
                $shipDelayHours = fake()->numberBetween(8, 38);
                $transitHours = max(18, (int) round($courier['speed'] * fake()->randomFloat(2, 0.72, 1.65)));
                $shippedOn = $date->addHours($shipDelayHours);
                $expectedDeliveryOn = $shippedOn->addHours($courier['speed'] + 22);
                $deliveredOn = $isRto || $isLost ? null : $shippedOn->addHours($transitHours);
                $rtoOn = $isRto ? $shippedOn->addHours($transitHours + fake()->numberBetween(30, 96)) : null;
                $status = $isLost ? 'lost' : ($isRto ? 'rto' : 'delivered');
                $rtoReasonName = $isRto ? fake()->randomElement($reasonNames) : null;

                $orderId = DB::table('orders')->insertGetId([
                    'order_number' => 'WS-' . $orderCounter++,
                    'order_date' => $date->toDateString(),
                    'customer_state' => $location['state'],
                    'customer_city' => $location['city'],
                    'product_category' => fake()->randomElement($categories),
                    'order_value' => $orderValue,
                    'payment_method' => $paymentMethod,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $shipmentId = DB::table('shipments')->insertGetId([
                    'order_id' => $orderId,
                    'courier_id' => $courier['id'],
                    'rto_reason_id' => $rtoReasonName ? $rtoReasonIds[$rtoReasonName] : null,
                    'tracking_number' => $courier['code'] . '-' . fake()->unique()->numerify('########'),
                    'shipped_on' => $shippedOn->toDateString(),
                    'expected_delivery_on' => $expectedDeliveryOn->toDateString(),
                    'delivered_on' => $deliveredOn?->toDateString(),
                    'rto_on' => $rtoOn?->toDateString(),
                    'status' => $status,
                    'ship_time_hours' => $shipDelayHours,
                    'shipping_cost' => round($courier['base_cost'] * fake()->randomFloat(2, 0.92, 1.34), 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($isLost) {
                    $claimFiled = fake()->boolean(88);
                    $claimAmount = round($orderValue + fake()->randomFloat(2, 80, 220), 2);
                    $recoveryRate = $claimFiled ? fake()->randomFloat(2, 0.15, 0.9) : 0;

                    DB::table('lost_cases')->insert([
                        'shipment_id' => $shipmentId,
                        'case_number' => 'LC-' . $lostCounter++,
                        'reported_on' => $date->addDays(fake()->numberBetween(3, 9))->toDateString(),
                        'status' => fake()->randomElement(['open', 'under_review', 'approved', 'recovered']),
                        'claim_filed' => $claimFiled,
                        'claim_amount' => $claimAmount,
                        'amount_recovered' => round($claimAmount * $recoveryRate, 2),
                        'notes' => 'Auto-seeded lost shipment case for dashboard analysis.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
