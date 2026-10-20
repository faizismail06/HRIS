<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrototypeDashboardTest extends TestCase
{
    public function test_preview_is_disabled_by_default(): void
    {
        config(['hris.ui_preview_enabled' => false]);

        $this->get('/prototype/dashboard')->assertNotFound();
    }

    public function test_preview_displays_only_synthetic_data_when_enabled(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->get('/prototype/dashboard')
            ->assertOk()
            ->assertSee('Data sintetis')
            ->assertSee('Nadia Pramesti')
            ->assertSee('Koreksi jam pulang')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/')->assertRedirect('/prototype/dashboard');
    }

    public function test_cached_preview_route_cannot_expose_fixtures_in_production(): void
    {
        // Simulate a route cache made locally then mistakenly used in production.
        config(['hris.ui_preview_enabled' => true]);
        $this->app['env'] = 'production';

        $this->get('/prototype/dashboard')->assertNotFound();
    }

    public function test_preview_has_no_mutation_endpoint(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->post('/prototype/dashboard')->assertStatus(405);
    }
}
