<?php

namespace Database\Seeders;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\AccountAccess;
use App\Domain\Identity\Audit;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public const ACCOUNTS = [
        'platform.admin@hris.test' => ['Platform Admin Lokal', 'platform_admin'],
        'hr.admin@hris.test' => ['HR Admin Lokal', 'hr_admin'],
        'hr.reviewer@hris.test' => ['HR Reviewer Lokal', 'hr_admin'],
        'hr.officer@hris.test' => ['HR Officer Lokal', 'hr_officer'],
        'payroll@hris.test' => ['Payroll Officer Lokal', 'payroll_officer'],
        'finance@hris.test' => ['Finance Approver Lokal', 'finance_approver'],
        'assets@hris.test' => ['IT Asset Officer Lokal', 'it_asset_officer'],
        'auditor@hris.test' => ['Auditor Lokal', 'auditor'],
    ];

    public function run(): void
    {
        throw_unless(app()->environment(['local', 'testing']) && ! DemoBoundary::enabled(), \RuntimeException::class, 'UserSeeder hanya untuk pengembangan lokal non-demo.');
        DemoBoundary::database();

        $credentials = DB::transaction(function () {
            $credentials = [];
            Company::firstOrCreate(['id' => 1], ['name' => 'Perusahaan Lokal HRIS']);
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();

            foreach (self::ACCOUNTS as $email => [$name, $role]) {
                // Never reset passwords, reactivate accounts or grant access to an existing identity.
                if (User::where('email', $email)->exists()) {
                    continue;
                }

                $user = new User;
                $password = Str::password(20);
                $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'is_active' => true, 'email_verified_at' => now()]);
                $grant = ['role' => $role, 'scope' => 'company'];
                app(AccountAccess::class)->validate($user, [$grant]);
                $user->save();
                $user->grants()->create($grant);
                Audit::record(null, 'account.seeded_local', 'user', $user->id, ['role' => $role, 'scope' => 'company']);
                $credentials[] = [$email, $password];
            }

            return $credentials;
        });

        if ($credentials && $this->command) {
            $this->command->table(['Email', 'Password awal (simpan sekarang)'], $credentials);
        }
        $this->command?->info('MFA tetap diaktifkan lewat /login; akun yang sudah ada tidak diubah. Password akun baru hanya ditampilkan saat pembuatan.');
    }
}
