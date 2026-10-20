<?php

namespace App\Console\Commands;

use App\Domain\Identity\Access;
use App\Domain\Identity\AccountAccess;
use App\Domain\Identity\Audit;
use App\Models\Company;
use App\Models\User;
use App\Rules\AccountPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProvisionAccount extends Command
{
    protected $signature = 'hris:provision-account {role : Role akun} {--bootstrap : Buat perusahaan dan admin platform pertama} {--company= : Nama perusahaan untuk bootstrap} {--employee= : ID karyawan untuk self/team} {--scope=company : company, branch, department, self atau team} {--target= : ID cabang atau departemen}';

    protected $description = 'Buat akun melalui terminal tepercaya dengan kata sandi tersembunyi dan MFA wajib';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Gunakan terminal interaktif agar kata sandi tidak berada di argumen atau riwayat shell.');

            return self::FAILURE;
        }
        $bootstrap = (bool) $this->option('bootstrap');
        if ($bootstrap && ($this->argument('role') !== 'platform_admin' || $this->option('scope') !== 'company' || Company::exists())) {
            $this->error('Bootstrap hanya untuk admin platform pertama pada instalasi tanpa perusahaan.');

            return self::FAILURE;
        }
        if (! $bootstrap && ! Company::exists()) {
            $this->error('Jalankan bootstrap admin platform terlebih dahulu.');

            return self::FAILURE;
        }
        $data = [
            'name' => $this->ask('Nama lengkap'),
            'email' => mb_strtolower(trim((string) $this->ask('Email kerja'))),
            'password' => $this->secret('Kata sandi'),
            'password_confirmation' => $this->secret('Ulangi kata sandi'),
            'role' => $this->argument('role'),
            'company' => $this->option('company'),
            'employee_id' => $this->option('employee'),
        ];
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', new AccountPassword, Password::min(12)->mixedCase()->numbers(), 'confirmed'],
            'role' => ['required', Rule::in(Access::ROLES)],
            'company' => [$bootstrap ? 'required' : 'nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id', 'unique:users,employee_id'],
        ])->validate();
        $user = DB::transaction(function () use ($data, $bootstrap) {
            if ($bootstrap) {
                Company::create(['name' => $data['company']]);
            }
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'employee_id' => $data['employee_id'], 'is_active' => true]);
            $grant = ['role' => $data['role'], 'scope' => $this->option('scope'), 'branch_id' => null, 'department_id' => null];
            if ($grant['scope'] === 'branch') {
                $grant['branch_id'] = $this->option('target');
            }
            if ($grant['scope'] === 'department') {
                $grant['department_id'] = $this->option('target');
            }
            app(AccountAccess::class)->validate($user, [$grant]);
            $user->save();
            $user->grants()->create($grant);
            Audit::record(null, 'account.provisioned_via_console', 'user', $user->id, ['role' => $data['role'], 'scope' => $grant['scope']]);

            return $user;
        });
        $this->info('Akun #'.$user->id.' dibuat. Masuk melalui /login, lalu aktifkan autentikator.');

        return self::SUCCESS;
    }
}
