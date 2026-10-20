<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CoreWrite
{
    public static function actor(User $actor, string $permission): User
    {
        DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
        $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
        abort_unless(AccountSecurity::available($fresh) && Access::companyPermission($fresh, $permission), 403);
        abort_unless((int) $fresh->security_version === (int) $actor->security_version, 403);

        return $fresh;
    }

    public static function version(int $actual, int $submitted): void
    {
        abort_unless($actual === $submitted, 409, 'Data berubah sejak formulir dibuka. Muat ulang formulir dan tinjau data terbaru sebelum menyimpan.');
    }

    public static function run(callable $operation): mixed
    {
        try {
            return DB::transaction($operation);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23514', '23503', '23505', '23P01'])) {
                throw ValidationException::withMessages(['record' => 'Data tidak dapat disimpan: kode/email duplikat, referensi tidak sesuai, rentang bertumpuk, atau struktur membentuk siklus. Periksa isian dan coba kembali.']);
            }
            throw $exception;
        }
    }
}
