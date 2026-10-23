<?php

namespace Tests\Feature;

use App\Domain\Identity\MfaPolicy;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class LocalMfaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
        Company::create(['name' => 'Local MFA Test']);
        config(['hris.local_skip_mfa' => true]);
        // These tests emulate APP_ENV=local, where Laravel's automatic test CSRF exemption no longer applies.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }

    public function test_local_password_login_without_enrollment_can_open_workspace(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();
        $user->grants()->create(['role' => 'hr_admin', 'scope' => 'company']);
        $this->get('/login')->assertSee('Mode testing lokal');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/app');
        $this->get('/app')->assertOk();
        $this->get('/app/employees/create')->assertOk();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_enrolled_local_user_skips_challenge_but_password_is_still_required(): void
    {
        $this->app['env'] = 'local';
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true, 'two_factor_secret' => Fortify::currentEncrypter()->encrypt('TESTINGSECRETKEYA'), 'two_factor_confirmed_at' => now()])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/app');
        $this->get('/app')->assertOk();
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_flag_is_ignored_outside_local_and_in_demo(): void
    {
        foreach (['testing', 'staging', 'production', 'demo'] as $environment) {
            $this->app['env'] = $environment;
            $this->assertFalse(MfaPolicy::skippedForLocalTesting());
        }
        $this->app['env'] = 'local';
        config(['hris.demo_features_enabled' => true]);
        $this->assertFalse(MfaPolicy::skippedForLocalTesting());
    }

    public function test_turning_flag_off_restores_enrollment_requirement(): void
    {
        $this->app['env'] = 'local';
        config(['hris.local_skip_mfa' => false]);
        $user = User::factory()->create();
        $user->forceFill(['is_active' => true])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/app');
        $this->get('/app')->assertRedirect('/account/security');
    }
}
