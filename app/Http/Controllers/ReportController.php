<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Reports\ReportExports;
use App\Domain\Reports\ReportService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function snapshot(Request $r, int $id)
    {
        $s = DB::table('attendance_snapshots')->where('id', $id)->firstOrFail();
        $snapshot = json_decode($s->snapshot, true);
        abort_unless(EmployeeVisibility::query($r->user(), $snapshot['work_date'])->whereKey($snapshot['employee_id'])->exists(), 404);

        return response()->json(['id' => $id, 'revision' => $s->revision, 'sha256' => $s->sha256, 'snapshot' => $snapshot])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $r, string $kind, ReportService $s)
    {
        return response()->json($s->build($r->user(), $kind, $r->all()))->header('Cache-Control', 'no-store, private');
    }

    public function export(OperationalRequest $r, string $kind, ReportExports $s)
    {
        return response()->json($s->request($r->user(), $kind, $r->key(), $r->all()), 202);
    }

    public function status(Request $r, int $id, ReportExports $s)
    {
        return response()->json($s->status($r->user(), $id));
    }

    public function download(Request $r, int $id, ReportExports $s)
    {
        return $s->download($r->user(), $id);
    }
}
