<?php

namespace App\Domain\Operations;

use App\Domain\Identity\AccountSecurity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Mutation
{
    /** Authorization is evaluated on every invocation, including a cached retry. Results contain references, never secrets. */
    public static function run(User $actor, string $operation, string $key, array $input, callable $authorize, callable $work): array
    {
        Validator::make(['key' => $key], ['key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $operation, $key, $input, $authorize, $work) {
            DB::table('companies')->where('id', 1)->lockForUpdate()->firstOrFail();
            $fresh = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless(AccountSecurity::available($fresh) && (int) $fresh->security_version === (int) $actor->security_version, 403);
            $authorize($fresh);
            $hash = hash('sha256', json_encode(self::canonical($input), JSON_THROW_ON_ERROR));
            $receipt = DB::table('mutation_receipts')->where('user_id', $actor->id)->where('operation', $operation)->where('key', $key)->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->input_hash, $hash), 409, 'Kunci retry sudah digunakan untuk isian berbeda.');

                return json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR);
            }
            $result = $work($fresh);
            DB::table('mutation_receipts')->insert(['user_id' => $actor->id, 'operation' => $operation, 'key' => $key, 'input_hash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $result;
        }, 3);
    }

    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::canonical($item);
            }
        }

        return $value;
    }
}
