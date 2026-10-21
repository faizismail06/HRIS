<?php

namespace App\Domain\Identity;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Operations\Mutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountAdministration
{
    public function access(User $actor, int $id, string $key, int $version, array $input): array
    {
        $this->authorize($actor);
        $data = Validator::make($input, ['is_active' => ['required', 'boolean'], 'auditor_export_enabled' => ['sometimes', 'boolean'], 'grants' => ['present', 'array', 'max:30'], 'grants.*' => ['array:role,scope,branch_id,department_id']])->validate();

        return Mutation::run($actor, 'account.access', $key, compact('id', 'version', 'data'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $data) {
            $target = User::findOrFail($id);
            CoreWrite::version($target->security_version, $version);
            app(AccountAccess::class)->replace($u, $target, $data['grants'], (bool) $data['is_active'], (bool) ($data['auditor_export_enabled'] ?? false));

            return ['id' => $id, 'security_version' => $target->fresh()->security_version];
        });
    }

    public function suspend(User $actor, int $id, string $key, int $version, array $input): array
    {
        $this->authorize($actor);
        $data = Validator::make($input, ['starts_at' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP', 'after:starts_at'], 'reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'account.suspend', $key, compact('id', 'version', 'data'), fn ($u) => $this->authorize($u), function ($u) use ($id, $version, $data) {
            $target = User::findOrFail($id);
            CoreWrite::version($target->security_version, $version);
            if ($target->grants()->where('role', 'platform_admin')->where('scope', 'company')->exists()) {
                $other = User::whereKeyNot($id)->whereHas('grants', fn ($q) => $q->where('role', 'platform_admin')->where('scope', 'company'))->get()->contains(fn ($candidate) => AccountSecurity::available($candidate) && ! DB::table('account_suspensions')->where('user_id', $candidate->id)->whereNull('revoked_at')->exists());
                abort_unless($other, 422, 'Admin platform terakhir tidak dapat disuspensi.');
            }
            $suspension = DB::table('account_suspensions')->insertGetId(['user_id' => $id, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null, 'reason' => encrypt($data['reason']), 'created_by' => $u->id, 'created_at' => now()]);
            User::whereKey($id)->increment('security_version', 1, ['remember_token' => null]);
            Audit::record($u->id, 'account.suspended', 'user', $id, ['suspension_id' => $suspension, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null]);

            return ['id' => $suspension, 'user_id' => $id, 'security_version' => $version + 1];
        });
    }

    public function revokeSuspension(User $actor, int $id, string $key, string $reason): array
    {
        $this->authorize($actor);
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'account.suspension.revoke', $key, compact('id', 'reason'), fn ($u) => $this->authorize($u), function ($u) use ($id) {
            $s = DB::table('account_suspensions')->where('id', $id)->firstOrFail();
            abort_if($s->revoked_at, 409);
            DB::table('account_suspensions')->where('id', $id)->update(['revoked_at' => now()]);
            User::whereKey($s->user_id)->increment('security_version', 1, ['remember_token' => null]);
            Audit::record($u->id, 'account.suspension_revoked', 'user', $s->user_id, ['suspension_id' => $id, 'reason_provided' => true]);

            return ['id' => $id, 'status' => 'revoked'];
        });
    }

    private function authorize(User $actor): void
    {
        abort_unless(Access::companyPermission($actor, 'accounts.manage'), 403);
    }
}
