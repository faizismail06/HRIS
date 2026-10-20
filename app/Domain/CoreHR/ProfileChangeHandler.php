<?php

namespace App\Domain\CoreHR;

use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowHandler;
use App\Models\EmployeePrivateProfile;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileChangeHandler implements WorkflowHandler
{
    public function __construct(private SensitiveProfiles $profiles, private string $category = 'general') {}

    public function approverRoles(): array
    {
        return in_array($this->category, ['bank', 'tax'], true) ? ['finance_approver'] : ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        $data = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'category' => ['required', Rule::in([$this->category])], 'base_version' => ['required', 'integer', 'min:0'], 'data' => ['required', 'array', 'min:1'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        abort_unless(SensitiveProfiles::allowed($actor, $data['employee_id'], $data['category']), 404);
        $data['data'] = $this->profiles->validate($data['category'], $data['data']);
        $this->version($data);

        return ['employee_id' => $data['employee_id'], 'payload' => $data, 'summary' => ['category' => $data['category'], 'fields' => array_keys($data['data']), 'base_version' => $data['base_version']]];
    }

    public function submitted(WorkflowRequest $r): void
    {
        $this->version($r->payload);
    }

    public function approved(WorkflowRequest $r): void
    {
        $data = $r->payload;
        $this->version($data);
        $values = $this->profiles->validate($data['category'], $data['data']);
        $before = EmployeePrivateProfile::where('employee_id', $r->employee_id)->where('category', $this->category)->first();
        $hash = $this->category === 'identity' ? SensitiveProfiles::nationalHash($values['national_id']) : null;
        abort_if($hash && EmployeePrivateProfile::where('national_id_hash', $hash)->where('employee_id', '!=', $r->employee_id)->exists(), 422, 'Identitas sudah digunakan profil lain; periksa kasus rehire.');
        $version = $data['base_version'] + 1;
        DB::table('private_profile_revisions')->insert(['employee_id' => $r->employee_id, 'category' => $this->category, 'version' => $version, 'before_data' => $before ? encrypt($before->data) : null, 'after_data' => encrypt($values), 'workflow_request_id' => $r->id, 'verified_by' => $r->effectActorId(), 'created_at' => now()]);
        EmployeePrivateProfile::updateOrCreate(['employee_id' => $r->employee_id, 'category' => $this->category], ['data' => $values, 'national_id_hash' => $hash, 'version' => $version, 'workflow_request_id' => $r->id, 'verified_by' => $r->effectActorId(), 'verified_at' => now()]);
        Audit::record($r->effectActorId(), 'profile.verified', 'employee', $r->employee_id, ['category' => $this->category, 'version' => $version, 'fields' => array_keys($values), 'request_id' => $r->id]);
    }

    public function released(WorkflowRequest $r): void
    {
        // No profile values or balance are reserved before final verification.
    }

    private function version(array $data): void
    {
        $version = EmployeePrivateProfile::where('employee_id', $data['employee_id'])->where('category', $this->category)->value('version') ?? 0;
        CoreWrite::version((int) $version, (int) $data['base_version']);
    }
}
