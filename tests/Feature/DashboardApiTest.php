<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_marketing_api_endpoints_return_successful_json_responses(): void
    {
        $this->getJson('/api/marketing/overview')->assertOk()->assertJsonStructure(['filters', 'data', 'alerts']);
        $this->getJson('/api/marketing/platform?platform=meta')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/marketing/campaigns')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/marketing/trends')->assertOk()->assertJsonStructure(['filters', 'data']);
    }

    public function test_operations_api_endpoints_return_successful_json_responses(): void
    {
        $this->getJson('/api/ops/overview')->assertOk()->assertJsonStructure(['filters', 'data', 'alerts']);
        $this->getJson('/api/ops/couriers')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/rto')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/lost-cases')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/trends')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/shipments')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/orders')->assertOk()->assertJsonStructure(['filters', 'data']);
        $this->getJson('/api/ops/rto-reasons')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_rto_reasons_can_be_managed(): void
    {
        $create = $this->postJson('/api/ops/rto-reasons', [
            'reason' => 'Address landmark missing',
            'category' => 'Address',
            'is_controllable' => true,
        ])->assertCreated();

        $id = $create->json('id');

        $this->patchJson("/api/ops/rto-reasons/{$id}", [
            'reason' => 'Address landmark not shared',
            'category' => 'Address',
            'is_controllable' => true,
        ])->assertOk();

        $this->deleteJson("/api/ops/rto-reasons/{$id}")->assertOk();
    }

    public function test_ai_assistant_returns_inline_answer_with_local_fallback(): void
    {
        Config::set('services.openai.key', null);

        $this->postJson('/assistant/query', [
            'department' => 'operations',
            'question' => 'Which courier should I suspend?',
            'context' => [
                'overview' => ['data' => ['total_orders' => 100, 'otd_percent' => 88, 'rto_rate' => 12, 'lost_cases' => 2]],
                'couriers' => ['data' => [
                    ['name' => 'Test Courier', 'rto_percent' => 14, 'otd_percent' => 81, 'lost_count' => 2, 'performance_score' => 65],
                ]],
                'rto' => ['data' => [['reason' => 'Customer unavailable', 'rto_count' => 9]]],
            ],
        ])->assertOk()->assertJsonStructure(['answer', 'session_key', 'messages', 'mode']);
    }

    public function test_ai_assistant_can_answer_platform_profitability_from_combined_context(): void
    {
        Config::set('services.openai.key', null);

        $this->postJson('/assistant/query', [
            'department' => 'marketing',
            'question' => 'which platform is profitable',
            'context' => [
                'marketing' => [
                    'overview' => ['data' => ['blended_roas' => 3.2, 'total_spend' => 100000, 'revenue' => 320000, 'conversions' => 500]],
                    'campaigns' => ['data' => []],
                    'platforms' => [
                        ['data' => ['platform_name' => 'Meta Ads', 'roas' => 2.4, 'revenue' => 120000, 'cac' => 650]],
                        ['data' => ['platform_name' => 'Google Ads', 'roas' => 3.8, 'revenue' => 200000, 'cac' => 420]],
                    ],
                ],
                'operations' => ['overview' => ['data' => []]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('mode', 'local_fallback')
            ->assertSee('Google Ads');
    }
}
