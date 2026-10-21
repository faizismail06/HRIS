<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\CloseService;
use App\Domain\Identity\Access;
use App\Http\Requests\OperationalRequest;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AttendancePeriodWorkspaceController extends Controller
{
    public function index(Request $r)
    {
        $this->authorizeClose($r);

        return view('workspace.periods.index', ['periods' => DB::table('attendance_periods')->join('branches', 'branches.id', '=', 'attendance_periods.branch_id')->orderByDesc('attendance_periods.id')->select('attendance_periods.*', 'branches.name as branch_name')->paginate(20), 'branches' => Branch::orderBy('name')->get()]);
    }

    public function store(OperationalRequest $r, CloseService $service)
    {
        $this->authorizeClose($r);
        $data = $r->validate(['branch_id' => ['required', 'integer'], 'starts_on' => ['required', 'date_format:Y-m-d'], 'last_work_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on']]);

        return $this->mutate(function () use ($r, $data, $service) {
            $result = $service->create($r->user(), $r->key(), ['branch_id' => $data['branch_id'], 'starts_on' => $data['starts_on'], 'ends_on' => CarbonImmutable::parse($data['last_work_date'])->addDay()->toDateString()]);

            return to_route('workspace.periods.show', $result['id'])->with('status', 'Periode dibuat. Jalankan tinjauan untuk memeriksa kehadiran dan penghambat.');
        });
    }

    public function show(Request $r, string $id)
    {
        $this->authorizeClose($r);
        $period = DB::table('attendance_periods')->where('id', $id)->firstOrFail();
        $d = $r->validate(['revision' => ['nullable', 'integer', 'min:1', 'max:'.max(1, $period->revision)], 'blocker_page' => ['nullable', 'integer', 'min:1']]);
        $revision = $d['revision'] ?? $period->revision;
        // Read the exact persisted review response. GET never advances the period version.
        $receipt = $period->status === 'review' ? DB::table('mutation_receipts')->where('operation', 'attendance-period.review')->whereRaw("result->>'id' = ?", [(string) $period->id])->whereRaw("result->>'version' = ?", [(string) $period->version])->latest('id')->value('result') : null;
        $review = $receipt ? json_decode($receipt, true, 512, JSON_THROW_ON_ERROR) : null;
        $page = (int) ($d['blocker_page'] ?? 1);
        $blockers = new LengthAwarePaginator(array_slice($review['blockers'] ?? [], ($page - 1) * 50, 50), count($review['blockers'] ?? []), 50, $page, ['path' => route('workspace.periods.show', $id), 'pageName' => 'blocker_page', 'query' => $r->except('blocker_page')]);
        $blockerPeople = DB::table('employees')->whereIn('id', collect($blockers->items())->pluck('employee_id')->filter()->unique())->get(['id', 'name', 'employee_number'])->keyBy('id');
        $snapshots = DB::table('attendance_snapshots')->where('attendance_period_id', $period->id)->where('revision', $revision)->orderBy('id')->paginate(50)->withQueryString();
        $snapshots->setCollection($snapshots->getCollection()->map(fn ($row) => ['id' => $row->id, 'sha256' => $row->sha256, 'data' => json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR)]));

        return view('workspace.periods.show', ['period' => $period, 'branch' => Branch::findOrFail($period->branch_id), 'review' => $review, 'blockers' => $blockers, 'blockerPeople' => $blockerPeople, 'snapshots' => $snapshots, 'revision' => $revision]);
    }

    public function action(OperationalRequest $r, string $id, string $action, CloseService $service)
    {
        $this->authorizeClose($r);

        return $this->mutate(function () use ($r, $id, $action, $service) {
            $version = $r->version();
            if ($action === 'close') {
                $r->validate(['checksum' => ['required', 'string', 'size:64']]);
            }
            match ($action) {
                'review' => $service->review($r->user(), (int) $id, $version, $r->key()),
                'close' => $service->close($r->user(), (int) $id, $version, $r->key(), $r->input('checksum')),
                'reopen' => $service->reopen($r->user(), (int) $id, $version, $r->key(), (string) $r->input('reason')),
            };

            return to_route('workspace.periods.show', $id)->with('status', match ($action) {
                'review' => 'Tinjauan disimpan. Periksa daftar penghambat sebelum menutup.',
                'close' => 'Periode ditutup. Snapshot revisi tersimpan dan tidak dapat ditimpa.',
                'reopen' => 'Periode dibuka kembali. Snapshot lama tetap tersimpan; tinjauan baru wajib dilakukan sebelum penutupan berikutnya.',
            });
        });
    }

    private function authorizeClose(Request $r): void
    {
        abort_unless(Access::companyPermission($r->user(), 'attendance.close'), 403);
    }

    private function mutate(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['period' => $e->getMessage() ?: 'Periode belum dapat diproses. Muat ulang, tinjau sumber dan selesaikan penghambat.']);
        }
    }
}
