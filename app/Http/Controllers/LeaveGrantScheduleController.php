<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Access;
use App\Domain\Leave\GrantSchedules;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveGrantScheduleController extends Controller
{
    public function store(OperationalRequest $r, GrantSchedules $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function decide(OperationalRequest $r, int $id, GrantSchedules $s)
    {
        return response()->json($s->decide($r->user(), $id, $r->version(), $r->key(), $r->all()));
    }

    public function show(Request $r, int $id)
    {
        abort_unless(Access::companyPermission($r->user(), 'leave.manage'), 403);
        $s = DB::table('leave_grant_schedules')->where('id', $id)->firstOrFail();
        $s->employee_ids = json_decode($s->employee_ids, true);
        $s->expiry_rule = 'calendar_year_end';
        $s->evidence = decrypt($s->evidence);
        $s->stop_reason = $s->stop_reason ? decrypt($s->stop_reason) : null;

        return response()->json(['schedule' => $s, 'runs' => DB::table('leave_grant_runs')->where('leave_grant_schedule_id', $id)->orderByDesc('id')->paginate(100)])->header('Cache-Control', 'no-store, private');
    }
}
