<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_homepage_redirects_to_marketing_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/marketing');
    }

    public function test_department_dashboard_pages_render_successfully(): void
    {
        $this->get('/marketing')
            ->assertStatus(200)
            ->assertSee('Marketing Dashboard')
            ->assertSee('Campaign Performance');

        $this->get('/operations')
            ->assertStatus(200)
            ->assertSee('Operations Dashboard')
            ->assertSee('Courier Performance');
    }

    public function test_detail_pages_render_successfully(): void
    {
        foreach (['/campaigns', '/shipments', '/rto-reasons', '/lost-cases', '/orders', '/assistant'] as $path) {
            $this->get($path)->assertStatus(200);
        }
    }
}
