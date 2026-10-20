<?php

namespace App\Domain\Imports;

use App\Domain\Attendance\ScheduleService;
use App\Domain\CoreHR\CoreWrite;
use App\Domain\CoreHR\EmployeeFields;
use App\Domain\CoreHR\EmployeeWriter;
use App\Domain\CoreHR\OrganizationWriter;
use App\Domain\CoreHR\SensitiveProfiles;
use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Leave\LeaveService;
use App\Domain\Operations\Mutation;
use App\Domain\Payroll\CompensationHandler;
use App\Domain\Workflow\WorkflowService;
use App\Jobs\CommitImport;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImportService
{
    public const FIELDS = [
        'employees' => ['employee_number', 'name', 'work_email', 'starts_on', 'ends_on', 'branch_code', 'department_code', 'job_code', 'manager_employee_number', 'national_id', 'legal_name'],
        'branches' => ['code', 'name', 'timezone'], 'departments' => ['code', 'name', 'parent_code'], 'jobs' => ['code', 'name', 'department_code'],
        'rosters' => ['employee_number', 'work_date', 'shift_code', 'is_day_off'],
        'leave_credits' => ['employee_number', 'type_code', 'entitlement_year', 'valid_from', 'valid_until', 'minutes', 'source_key', 'reason'],
        'compensation' => ['employee_number', 'valid_from', 'monthly_base_minor', 'reason'],
    ];

    private function authorize(User $actor, string $kind): void
    {
        abort_unless(isset(self::FIELDS[$kind]), 422);
        $permission = match ($kind) {
            'employees' => 'employees.manage', 'branches', 'departments', 'jobs' => 'organization.manage', 'rosters' => 'schedules.manage', 'leave_credits' => 'leave.manage', 'compensation' => 'payroll.prepare'
        };
        abort_unless(Access::companyPermission($actor, $permission), 403);
    }

    private function owned(User $actor, int $id): object
    {
        $batch = DB::table('import_batches')->where('id', $id)->where('created_by', $actor->id)->firstOrFail();
        $this->authorize($actor, $batch->kind);

        return $batch;
    }

    public function upload(User $actor, string $kind, UploadedFile $file, string $key): array
    {
        $this->authorize($actor, $kind);
        $parsed = app(TabularReader::class)->read($file);
        $hash = hash_file('sha256', $file->getRealPath());

        return Mutation::run($actor, 'import.upload', $key, compact('kind', 'hash'), fn ($u) => $this->authorize($u, $kind), function ($u) use ($kind, $parsed, $hash) {
            $existing = DB::table('import_batches')->where('created_by', $u->id)->where('kind', $kind)->where('file_sha256', $hash)->first();
            if ($existing) {
                return ['id' => $existing->id, 'version' => $existing->version, 'status' => $existing->status];
            }
            $id = DB::table('import_batches')->insertGetId(['created_by' => $u->id, 'kind' => $kind, 'file_sha256' => $hash, 'headers' => json_encode($parsed['headers']), 'rows' => encrypt($parsed['rows']), 'row_count' => count($parsed['rows']), 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'import.uploaded', 'import_batch', $id, ['kind' => $kind, 'row_count' => count($parsed['rows'])]);

            return ['id' => $id, 'version' => 1, 'status' => 'uploaded'];
        });
    }

    public function map(User $actor, int $id, int $version, string $key, array $mapping): array
    {
        return Mutation::run($actor, 'import.map', $key, compact('id', 'version', 'mapping'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version, $mapping) {
            $b = $this->owned($u, $id);
            CoreWrite::version($b->version, $version);
            abort_if(in_array($b->status, ['committed', 'queued']), 409);
            abort_unless($mapping && ! array_diff(array_keys($mapping), self::FIELDS[$b->kind]) && count($mapping) === count(array_unique(array_values($mapping))) && ! array_diff(array_values($mapping), json_decode($b->headers, true)), 422, 'Mapping harus memakai field yang tersedia dan header unik.');
            DB::table('import_batches')->where('id', $id)->update(['mapping' => json_encode($mapping), 'status' => 'mapped', 'errors' => '[]', 'error_count' => 0, 'preview_checksum' => null, 'version' => $version + 1, 'updated_at' => now()]);

            return ['id' => $id, 'version' => $version + 1, 'status' => 'mapped'];
        });
    }

    public function preview(User $actor, int $id, int $version, string $key): array
    {
        return Mutation::run($actor, 'import.preview', $key, compact('id', 'version'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version) {
            $b = $this->owned($u, $id);
            CoreWrite::version($b->version, $version);
            abort_unless(in_array($b->status, ['mapped', 'invalid', 'validated', 'failed']), 409);
            $preview = $this->validate($u, $b);
            $status = $preview['error_count'] ? 'invalid' : 'validated';
            DB::table('import_batches')->where('id', $id)->update(['status' => $status, 'errors' => json_encode($preview['errors']), 'error_count' => $preview['error_count'], 'preview_checksum' => $preview['checksum'], 'version' => $version + 1, 'updated_at' => now()]);

            return ['id' => $id, 'version' => $version + 1, 'status' => $status, 'checksum' => $preview['checksum'], 'error_count' => $preview['error_count'], 'errors' => $preview['errors'], 'preview' => array_map(fn ($row) => ['row' => $row['row'], 'data' => array_intersect_key($row['data'], array_flip(['employee_number', 'name', 'code', 'work_date', 'valid_from', 'starts_on']))], array_slice($preview['rows'], 0, 20))];
        });
    }

    private function validate(User $actor, object $batch): array
    {
        $headers = json_decode($batch->headers, true);
        $mapping = json_decode($batch->mapping, true);
        $errors = [];
        $count = 0;
        $normalized = [];
        $seen = [];
        $national = [];
        if ($batch->kind === 'employees') {
            $national = DB::table('employee_private_profiles')->whereNotNull('national_id_hash')->pluck('employee_id', 'national_id_hash')->all();
            foreach (WorkflowRequest::where('kind', 'identity_change')->whereNotIn('status', ['rejected', 'withdrawn'])->cursor() as $r) {
                $national[SensitiveProfiles::nationalHash($r->payload['data']['national_id'])] = $r->employee_id;
            }
        }
        foreach (decrypt($batch->rows) as $index => $values) {
            $row = [];
            foreach ($mapping as $field => $header) {
                $row[$field] = $values[array_search($header, $headers, true)];
            }
            try {
                $d = $this->normalize($actor, $batch->kind, $row);
                $keys = match ($batch->kind) {
                    'employees' => ['employee_number' => $d['employee_number'], 'work_email' => strtolower($d['work_email'] ?? ''), 'national_id' => isset($d['national_id']) ? SensitiveProfiles::nationalHash($d['national_id']) : ''], 'rosters' => ['work_date' => $d['employee_id'].'|'.$d['work_date']], 'leave_credits' => ['source_key' => $d['source_key']], 'compensation' => ['valid_from' => $d['employee_id'].'|'.$d['valid_from']], default => ['code' => $d['code']]
                };
                foreach ($keys as $field => $value) {
                    if ($value === '') {
                        continue;
                    }
                    if (isset($seen[$field][$value]) || ($field === 'national_id' && isset($national[$value]))) {
                        throw ValidationException::withMessages([$field => 'Duplikat dalam batch atau profil sebelumnya. Gunakan person lama dan alur rehire; jangan overwrite.']);
                    }
                    $seen[$field][$value] = true;
                }
                $normalized[] = ['row' => $index + 2, 'data' => $d];
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $count++;
                    if (count($errors) < 200) {
                        $errors[] = ['row' => $index + 2, 'column' => $mapping[$field] ?? $field, 'reason' => $messages[0], 'remedy' => 'Perbaiki nilai/duplikat atau pilih referensi aktif yang sudah diimpor, lalu preview ulang.'];
                    }
                }
            } catch (HttpException $e) {
                if (! in_array($e->getStatusCode(), [404, 409, 422])) {
                    throw $e;
                }
                $count++;
                if (count($errors) < 200) {
                    $errors[] = ['row' => $index + 2, 'column' => 'references', 'reason' => 'Referensi, interval atau scope tidak valid.', 'remedy' => 'Periksa employee/master aktif, job-department dan tanggal; impor atasan lebih dahulu.'];
                }
            }
        }
        if ($batch->kind === 'rosters' && ! $count) {
            try {
                $roster = app(ScheduleService::class)->preview($actor, ['rows' => array_column($normalized, 'data')]);
                if (! empty($roster['conflicts'])) {
                    $count++;
                    $errors[] = ['row' => null, 'column' => 'work_date', 'reason' => 'Roster memiliki benturan.', 'remedy' => 'Periksa preview roster dan versi jadwal yang sudah diterbitkan.'];
                }
            } catch (HttpException|ValidationException $e) {
                $count++;
                $errors[] = ['row' => null, 'column' => 'roster', 'reason' => 'Roster tidak dapat diterbitkan dari sumber ini.', 'remedy' => 'Periksa penempatan, shift dan waktu yang bertumpuk.'];
            }
        }

        return ['rows' => $normalized, 'errors' => $errors, 'error_count' => $count, 'checksum' => hash_hmac('sha256', json_encode([$mapping, $normalized], JSON_THROW_ON_ERROR), config('app.key'))];
    }

    private function reference(string $table, string $column, string $value, bool $active = true): int
    {
        $q = DB::table($table)->where($column, $value);
        if ($active) {
            $q->where('is_active', true);
        }
        $id = $q->value('id');
        if (! $id) {
            throw ValidationException::withMessages([$column => 'Referensi belum ada atau tidak aktif; impor referensi lebih dahulu.']);
        }

        return $id;
    }

    private function normalize(User $actor, string $kind, array $row): array
    {
        if (in_array($kind, ['branches', 'departments', 'jobs'])) {
            $rules = ['code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9_-]{0,39}$/', 'unique:'.($kind === 'jobs' ? 'job_positions' : $kind).',code'], 'name' => ['required', 'string', 'max:160']];
            if ($kind === 'branches') {
                $rules['timezone'] = ['required', 'timezone'];
            }
            $d = Validator::make($row, $rules)->validate() + ['is_active' => true];
            if ($kind === 'departments') {
                $d['parent_id'] = empty($row['parent_code']) ? null : $this->reference('departments', 'code', $row['parent_code']);
            }
            if ($kind === 'jobs') {
                $d['department_id'] = $this->reference('departments', 'code', $row['department_code'] ?? '');
            }

            return $d;
        }
        if ($kind === 'employees') {
            $row['work_email'] = empty($row['work_email']) ? null : strtolower($row['work_email']);
            $row['ends_on'] = $row['ends_on'] ?? null;
            if ($row['ends_on'] === '') {
                $row['ends_on'] = null;
            }
            $d = Validator::make($row, ['employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'], 'name' => ['required', 'string', 'max:160'], 'work_email' => ['nullable', 'email', 'max:255', 'unique:employees,work_email'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after:starts_on'], 'national_id' => ['nullable', 'regex:/^[0-9]{16}$/'], 'legal_name' => ['required_with:national_id', 'nullable', 'string', 'max:160']])->validate();
            if ($d['work_email'] && DB::table('employees')->whereRaw('lower(work_email) = ?', [$d['work_email']])->exists()) {
                throw ValidationException::withMessages(['work_email' => 'Email sudah digunakan; gunakan person lama, bukan overwrite.']);
            }
            if (empty($d['national_id'])) {
                unset($d['national_id'], $d['legal_name']);
            }
            $d['branch_id'] = $this->reference('branches', 'code', $row['branch_code'] ?? '');
            $d['department_id'] = $this->reference('departments', 'code', $row['department_code'] ?? '');
            $d['job_id'] = $this->reference('job_positions', 'code', $row['job_code'] ?? '');
            $d['manager_employee_id'] = empty($row['manager_employee_number']) ? null : $this->reference('employees', 'employee_number', $row['manager_employee_number'], false);
            EmployeeFields::validatePlacement($d, $d['starts_on']);

            return $d;
        }
        $employee = $this->reference('employees', 'employee_number', $row['employee_number'] ?? '', false);
        if ($kind === 'rosters') {
            $d = Validator::make($row, ['work_date' => ['required', 'date_format:Y-m-d'], 'is_day_off' => ['required', 'boolean']])->validate();
            $d['employee_id'] = $employee;
            $d['is_day_off'] = (bool) $d['is_day_off'];
            $d['shift_template_id'] = $d['is_day_off'] ? null : $this->reference('shift_templates', 'code', $row['shift_code'] ?? '');

            return $d;
        }
        if ($kind === 'compensation') {
            $d = Validator::make($row, ['valid_from' => ['required', 'date_format:Y-m-d'], 'monthly_base_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'reason' => ['required', 'string', 'max:1000']])->validate();
            $d['employee_id'] = $employee;
            $d['monthly_base_minor'] = (int) $d['monthly_base_minor'];
            $d['base_compensation_id'] = DB::table('employee_compensations')->where('employee_id', $employee)->where('valid_from', '<=', $d['valid_from'])->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $d['valid_from']))->value('id');
            app(CompensationHandler::class)->prepare($actor, $d);

            return $d;
        }
        $d = Validator::make($row, ['type_code' => ['required', 'exists:leave_types,code'], 'entitlement_year' => ['required', 'integer', 'between:2000,2200'], 'valid_from' => ['required', 'date_format:Y-m-d'], 'valid_until' => ['required', 'date_format:Y-m-d', 'after:valid_from'], 'minutes' => ['required', 'integer', 'between:1,1000000'], 'source_key' => ['required', 'string', 'max:100', 'unique:leave_credit_lots,source_key'], 'reason' => ['required', 'string', 'max:1000']])->validate();
        foreach (['entitlement_year', 'minutes'] as $field) {
            $d[$field] = (int) $d[$field];
        }

        return $d + ['employee_id' => $employee, 'source_type' => 'grant'];
    }

    public function commit(User $actor, int $id, int $version, string $key, string $checksum): array
    {
        return CoreWrite::run(fn () => Mutation::run($actor, 'import.commit', $key, compact('id', 'version', 'checksum'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version, $checksum) {
            $b = $this->owned($u, $id);
            CoreWrite::version($b->version, $version);
            abort_unless(in_array($b->status, ['validated', 'queued']), 409);
            $current = $this->validate($u, $b);
            if ($current['error_count'] || ! hash_equals($b->preview_checksum, $checksum) || ! hash_equals($current['checksum'], $checksum)) {
                DB::table('import_batches')->where('id', $id)->update(['status' => 'invalid', 'version' => $version + 1, 'errors' => json_encode($current['errors']), 'error_count' => $current['error_count'], 'updated_at' => now()]);

                return ['id' => $id, 'version' => $version + 1, 'status' => 'invalid', 'http_status' => 409, 'code' => 'preview_stale_validate_again'];
            }
            $result = [];
            if ($b->kind === 'rosters') {
                $result[] = app(ScheduleService::class)->draft($u, (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'import-roster:'.$id), ['rows' => array_column($current['rows'], 'data')]);
            } else {
                foreach ($current['rows'] as $row) {
                    $d = $row['data'];
                    $rowKey = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'import:'.$id.':'.$row['row']);
                    if ($b->kind === 'employees') {
                        $employee = app(EmployeeWriter::class)->create($u, $d);
                        $record = ['id' => $employee->id];
                        if (isset($d['national_id'])) {
                            $record['identity_request'] = app(WorkflowService::class)->draft($u, $rowKey, 'identity_change', ['employee_id' => $employee->id, 'category' => 'identity', 'base_version' => 0, 'reason' => 'Imported identity requires independent verification', 'data' => array_intersect_key($d, ['national_id' => true, 'legal_name' => true])]);
                        }
                    } elseif (in_array($b->kind, ['branches', 'departments', 'jobs'])) {
                        $record = ['id' => app(OrganizationWriter::class)->save($u, $b->kind, null, $d)->id];
                    } elseif ($b->kind === 'leave_credits') {
                        $record = app(LeaveService::class)->grant($u, $rowKey, $d);
                    } else {
                        $record = app(WorkflowService::class)->draft($u, $rowKey, 'compensation', $d);
                    }
                    $result[] = ['row' => $row['row']] + $record;
                }
            }
            DB::table('import_batches')->where('id', $id)->update(['status' => 'committed', 'version' => $version + 1, 'result' => json_encode($result), 'updated_at' => now()]);
            Audit::record($u->id, 'import.committed', 'import_batch', $id, ['kind' => $b->kind, 'row_count' => $b->row_count]);

            return ['id' => $id, 'version' => $version + 1, 'status' => 'committed', 'created_count' => $b->row_count];
        }));
    }

    public function show(User $actor, int $id): array
    {
        $b = $this->owned($actor, $id);

        return ['id' => $id, 'kind' => $b->kind, 'headers' => json_decode($b->headers, true), 'available_fields' => self::FIELDS[$b->kind], 'mapping' => json_decode($b->mapping ?? '{}', true), 'row_count' => $b->row_count, 'status' => $b->status, 'version' => $b->version, 'errors' => json_decode($b->errors, true), 'error_count' => $b->error_count, 'result' => json_decode($b->result ?? '[]', true)];
    }

    public function queue(User $actor, int $id, int $version, string $key, string $checksum): array
    {
        return Mutation::run($actor, 'import.queue', $key, compact('id', 'version', 'checksum'), fn ($u) => $this->owned($u, $id), function ($u) use ($id, $version, $key, $checksum) {
            $b = $this->owned($u, $id);
            CoreWrite::version($b->version, $version);
            abort_unless($b->status === 'validated' && hash_equals($b->preview_checksum, $checksum), 409);
            DB::table('import_batches')->where('id', $id)->update(['status' => 'queued', 'version' => $version + 1, 'updated_at' => now()]);
            $workerKey = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'import-worker:'.$key);
            $securityVersion = $u->security_version;
            DB::afterCommit(fn () => CommitImport::dispatch($u->id, $securityVersion, $id, $version + 1, $workerKey, $checksum));

            return ['id' => $id, 'version' => $version + 1, 'status' => 'queued'];
        });
    }
}
