<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Access;
use App\Domain\Identity\AccountSecurity;
use App\Domain\Leave\GrantSchedules;
use App\Domain\Leave\LeaveService;
use App\Domain\Leave\LotAccounting;
use App\Http\Controllers\Concerns\HandlesWorkspaceWrites;
use App\Http\Requests\OperationalRequest;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaveWorkspaceController extends Controller
{
    use HandlesWorkspaceWrites;

    public function balances(Request $r, LeaveService $service)
    {
        $d = $r->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $rows = $service->balances($r->user());
        $page = (int) ($d['page'] ?? 1);

        return view('workspace.leave.balances', ['lots' => new LengthAwarePaginator(array_slice($rows, ($page - 1) * 30, 30), count($rows), 30, $page, ['path' => route('workspace.leave-balances')])]);
    }

    public function index(Request $r)
    {
        $this->authorizeLeave($r);
        $q = $this->search($r);
        $lots = DB::table('leave_credit_lots')->join('employees', 'employees.id', '=', 'leave_credit_lots.employee_id');
        if ($q !== '') {
            $lots->where(fn ($query) => $query->where('employees.name', 'ilike', '%'.$q.'%')->orWhere('employees.employee_number', 'ilike', '%'.$q.'%'));
        }

        return view('workspace.leave.index', ['q' => $q, 'lots' => $lots->orderByDesc('leave_credit_lots.id')->select('leave_credit_lots.*', 'employees.name as employee_name', 'employees.employee_number')->paginate(30)->withQueryString()]);
    }

    public function types(Request $r)
    {
        $this->authorizeLeave($r);

        return view('workspace.leave.types', ['types' => LeaveType::orderByDesc('id')->paginate(30)]);
    }

    public function storeType(OperationalRequest $r, LeaveService $service)
    {
        $this->authorizeLeave($r);

        return $this->workspaceWrite(function () use ($r, $service) {
            $service->type($r->user(), $r->key(), $r->only(['code', 'name', 'is_paid', 'deducts_balance', 'allows_half_day', 'requires_evidence', 'notice_days', 'minimum_tenure_days']));

            return to_route('workspace.leave.types')->with('status', 'Versi jenis cuti disimpan. Periksa jadwal hak yang masih merujuk versi lama.');
        });
    }

    public function grantForm(Request $r)
    {
        $this->authorizeLeave($r);
        $q = $this->search($r);

        return view('workspace.leave.grant', ['q' => $q, 'people' => $this->people($q), 'types' => LeaveType::where('is_active', true)->where('deducts_balance', true)->orderBy('name')->get()]);
    }

    public function grant(OperationalRequest $r, LeaveService $service)
    {
        $this->authorizeLeave($r);
        $r->validate(['last_valid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from']]);
        $data = $r->only(['employee_id', 'type_code', 'entitlement_year', 'valid_from', 'minutes', 'source_key', 'source_type', 'reason']);
        $data['valid_until'] = CarbonImmutable::parse($r->input('last_valid_on'))->addDay()->toDateString();

        return $this->workspaceWrite(function () use ($r, $service, $data) {
            $result = $service->grant($r->user(), $r->key(), $data);

            return to_route('workspace.leave.lots.show', $result['id'])->with('status', 'Kredit hak cuti dicatat ke ledger.');
        });
    }

    public function lot(Request $r, string $id)
    {
        $this->authorizeLeave($r);
        $lot = DB::table('leave_credit_lots')->where('id', $id)->firstOrFail();

        return view('workspace.leave.lot', ['lot' => $lot, 'person' => Employee::findOrFail($lot->employee_id), 'balance' => LotAccounting::balance($lot), 'canChange' => $r->user()->employee_id !== $lot->employee_id, 'ledger' => DB::table('leave_ledger')->where('leave_credit_lot_id', $lot->id)->orderByDesc('id')->paginate(30)]);
    }

    public function change(OperationalRequest $r, string $id, LotAccounting $service)
    {
        $this->authorizeLeave($r);
        $data = $r->only(['kind', 'minutes', 'reason']);
        if ($r->input('kind') === 'carry_out') {
            $r->validate(['valid_from' => ['required', 'date_format:Y-m-d'], 'last_valid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from']]);
            $data['valid_from'] = $r->input('valid_from');
            $data['valid_until'] = CarbonImmutable::parse($r->input('last_valid_on'))->addDay()->toDateString();
        }

        return $this->workspaceWrite(function () use ($r, $id, $data, $service) {
            $result = $service->change($r->user(), (int) $id, $r->key(), $data);

            return to_route('workspace.leave.lots.show', $result['target_lot_id'] ?? $id)->with('status', 'Perubahan hak cuti dicatat. Kredit yang sudah dipesan atau digunakan tetap dilindungi.');
        });
    }

    public function schedules(Request $r)
    {
        $this->authorizeLeave($r);
        $q = $this->search($r);

        return view('workspace.leave.schedules', ['q' => $q, 'people' => $this->people($q), 'types' => LeaveType::where('is_active', true)->where('deducts_balance', true)->orderBy('name')->get(), 'schedules' => DB::table('leave_grant_schedules')->orderByDesc('id')->paginate(20)->withQueryString()]);
    }

    public function storeSchedule(OperationalRequest $r, GrantSchedules $service)
    {
        $this->authorizeLeave($r);
        $r->validate(['last_schedule_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:first_grant_on']]);
        $data = $r->only(['leave_type_id', 'name', 'cadence', 'expiry_rule', 'first_grant_on', 'minutes', 'minimum_tenure_days', 'employee_ids', 'evidence']);
        $data['ends_on'] = CarbonImmutable::parse($r->input('last_schedule_on'))->addDay()->toDateString();

        return $this->workspaceWrite(function () use ($r, $data, $service) {
            $result = $service->create($r->user(), $r->key(), $data);

            return to_route('workspace.leave.schedules.show', $result['id'])->with('status', 'Jadwal disimpan sebagai draf. HR Admin lain yang bukan penerima harus mengesahkannya.');
        });
    }

    public function schedule(Request $r, string $id)
    {
        $this->authorizeLeave($r);
        $schedule = DB::table('leave_grant_schedules')->where('id', $id)->firstOrFail();
        $ids = json_decode($schedule->employee_ids, true, 512, JSON_THROW_ON_ERROR);
        $type = LeaveType::findOrFail($schedule->leave_type_id);
        $approver = $schedule->approved_by ? User::find($schedule->approved_by) : null;

        return view('workspace.leave.schedule', ['schedule' => $schedule, 'type' => $type, 'people' => Employee::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'employee_number']), 'evidence' => decrypt($schedule->evidence), 'stopReason' => $schedule->stop_reason ? decrypt($schedule->stop_reason) : null, 'canApprove' => $schedule->created_by !== $r->user()->id && ! in_array($r->user()->employee_id, $ids, true), 'authorityAvailable' => $approver && AccountSecurity::available($approver) && Access::companyPermission($approver, 'leave.manage'), 'runs' => DB::table('leave_grant_runs')->leftJoin('employees', 'employees.id', '=', 'leave_grant_runs.employee_id')->where('leave_grant_schedule_id', $schedule->id)->orderByDesc('leave_grant_runs.id')->select('leave_grant_runs.*', 'employees.name as employee_name')->paginate(30)]);
    }

    public function decideSchedule(OperationalRequest $r, string $id, GrantSchedules $service)
    {
        $this->authorizeLeave($r);

        return $this->workspaceWrite(function () use ($r, $id, $service) {
            $service->decide($r->user(), (int) $id, $r->version(), $r->key(), $r->only(['action', 'reason']));

            return to_route('workspace.leave.schedules.show', $id)->with('status', 'Keputusan jadwal hak cuti disimpan. Kredit yang sudah tercatat tidak dihapus.');
        });
    }

    private function authorizeLeave(Request $r): void
    {
        abort_unless(Access::companyPermission($r->user(), 'leave.manage'), 403);
    }

    private function search(Request $r): string
    {
        return $r->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';
    }

    private function people(string $q)
    {
        return Employee::when($q !== '', fn ($query) => $query->where(fn ($where) => $where->where('name', 'ilike', '%'.$q.'%')->orWhere('employee_number', 'ilike', '%'.$q.'%')))->orderBy('name')->limit(50)->get(['id', 'name', 'employee_number']);
    }
}
