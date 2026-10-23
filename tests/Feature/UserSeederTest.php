<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('hris_testing', DB::connection()->getDatabaseName());
    }

    public function test_accounts_have_real_permissions_and_seed_does_not_overwrite_existing_accounts(): void
    {
        Company::create(['name' => 'Existing Company']);
        $existing = User::factory()->create(['email' => 'hr.admin@hris.test']);
        $existing->forceFill(['is_active' => false])->save();
        $password = $existing->password;

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(8, User::count());
        $this->assertSame('Existing Company', Company::findOrFail(1)->name);
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertFalse($existing->fresh()->is_active);
        $this->assertSame(0, $existing->grants()->count());
        foreach (UserSeeder::ACCOUNTS as $email => [$name, $role]) {
            if ($email === $existing->email) {
                continue;
            }
            $user = User::where('email', $email)->firstOrFail();
            $this->assertFalse(Hash::needsRehash($user->password));
            $this->assertTrue($user->is_active);
            $this->assertNull($user->two_factor_confirmed_at);
            $this->assertDatabaseHas('role_grants', ['user_id' => $user->id, 'role' => $role, 'scope' => 'company']);
        }
        $this->assertSame(7, DB::table('audit_entries')->where('action', 'account.seeded_local')->count());
    }

    public function test_seeder_creates_company_on_empty_installation(): void
    {
        $this->seed(UserSeeder::class);
        $this->assertSame(8, User::count());
        $this->assertSame('Perusahaan Lokal HRIS', Company::findOrFail(1)->name);
    }

    public function test_seeder_refuses_production_before_writing(): void
    {
        $this->app['env'] = 'production';
        try {
            app(UserSeeder::class)->run();
            $this->fail('Local accounts must not be seeded in production.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('lokal non-demo', $exception->getMessage());
        }
        $this->assertSame(0, User::count());
        $this->assertSame(0, Company::count());
    }

    public function test_seeder_refuses_demo_before_writing(): void
    {
        config(['hris.demo_features_enabled' => true]);
        try {
            app(UserSeeder::class)->run();
            $this->fail('Local accounts must not be seeded in demo.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('lokal non-demo', $exception->getMessage());
        }
        $this->assertSame(0, User::count());
        $this->assertSame(0, Company::count());
    }
}
