<?php

namespace Database\Seeders;

use App\Models\AdPlatform;
use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = AdPlatform::query()->pluck('id', 'slug');

        $campaigns = [
            ['platform' => 'meta', 'name' => 'Meta Prospecting - Skincare Starters', 'objective' => 'Conversions', 'status' => 'active', 'daily_budget' => 18500],
            ['platform' => 'meta', 'name' => 'Meta Retargeting - Cart Recovery', 'objective' => 'Conversions', 'status' => 'active', 'daily_budget' => 8200],
            ['platform' => 'meta', 'name' => 'Meta Creative Test - Wellness Bundles', 'objective' => 'Creative Testing', 'status' => 'paused', 'daily_budget' => 5200],
            ['platform' => 'google', 'name' => 'Google Search - Brand Defense', 'objective' => 'Search Sales', 'status' => 'active', 'daily_budget' => 9500],
            ['platform' => 'google', 'name' => 'Google Shopping - Best Sellers', 'objective' => 'Shopping Sales', 'status' => 'active', 'daily_budget' => 14200],
            ['platform' => 'google', 'name' => 'Google PMax - New Customer Scale', 'objective' => 'New Customers', 'status' => 'learning', 'daily_budget' => 12000],
        ];

        foreach ($campaigns as $campaign) {
            Campaign::query()->create([
                'ad_platform_id' => $platforms[$campaign['platform']],
                'name' => $campaign['name'],
                'objective' => $campaign['objective'],
                'status' => $campaign['status'],
                'started_on' => now()->subMonths(3)->toDateString(),
                'ended_on' => null,
                'daily_budget' => $campaign['daily_budget'],
            ]);
        }
    }
}
