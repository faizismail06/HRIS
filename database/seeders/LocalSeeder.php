<?php

namespace Database\Seeders;

use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Access;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

abstract class LocalSeeder extends Seeder
{
    protected array $dependencies = [];

    final public function run(): void
    {
        throw_unless(app()->environment(['local', 'testing']) && ! DemoBoundary::enabled(), \RuntimeException::class, 'Seeder contoh hanya untuk lokal non-demo.');
        DemoBoundary::database();
        if ($this->dependencies) {
            $this->call($this->dependencies);
        }
        DB::transaction(function () {
            Company::firstOrCreate(['id' => 1], ['name' => 'Perusahaan Lokal HRIS']);
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $this->seedLocal();
        });
    }

    protected function hr(): User
    {
        $user = User::where('email', 'hr.admin@hris.test')->firstOrFail();
        throw_unless(Access::companyPermission($user, 'organization.manage'), \RuntimeException::class, 'Akun HR lokal tidak aktif/berizin. Seeder tidak mengubah izin akun lama.');

        return $user;
    }

    abstract protected function seedLocal(): void;
}
