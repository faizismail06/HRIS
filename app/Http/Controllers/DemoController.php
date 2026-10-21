<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceService;
use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Audit;
use App\Models\RosterShift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemoController extends Controller
{
    private function operator(Request $r): void
    {
        DemoBoundary::operator($r->header('X-Demo-Operator-Token'));
    }

    public function personas(Request $r)
    {
        $this->operator($r);

        return response()->json(['environment' => 'DEMO', 'scenario_id' => config('hris.demo_scenario_id'), 'personas' => DB::table('demo_personas')->where('scenario_id', config('hris.demo_scenario_id'))->pluck('code')]);
    }

    public function login(Request $r)
    {
        $this->operator($r);
        $d = $r->validate(['persona' => ['required', 'string', 'max:40'], 'employee_id' => ['prohibited'], 'scenario_id' => ['prohibited']]);
        $p = DB::table('demo_personas')->where('code', $d['persona'])->where('scenario_id', config('hris.demo_scenario_id'))->firstOrFail();
        $user = User::findOrFail($p->user_id);
        Auth::login($user);
        $r->session()->regenerate();
        $r->session()->put(['auth.security_version' => $user->security_version, 'auth.mfa_user' => $user->id]);
        Audit::record($user->id, 'demo.persona_selected', 'demo_scenario', null, ['scenario_id' => config('hris.demo_scenario_id'), 'persona' => $p->code, 'provenance' => 'SIMULATED']);

        return response()->json(['environment' => 'DEMO', 'persona' => $p->code]);
    }

    public function time(Request $r)
    {
        $this->operator($r);
        $d = $r->validate(['time' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'scenario_id' => ['prohibited']]);
        DB::transaction(function () use ($r, $d) {
            DB::table('installation_boundary')->where('id', 1)->lockForUpdate()->firstOrFail();
            DB::table('installation_boundary')->where('id', 1)->update(['scenario_time' => $d['time']]);
            Audit::record($r->user()?->id, 'demo.clock_set', 'demo_scenario', null, ['scenario_id' => config('hris.demo_scenario_id'), 'time' => $d['time']]);
        });

        return response()->json(['environment' => 'DEMO', 'time' => $d['time']]);
    }

    public function clock(Request $r, AttendanceService $s)
    {
        $this->operator($r);
        abort_unless($r->user(), 401);
        $d = $r->validate(['idempotency_key' => ['required', 'uuid'], 'roster_shift_id' => ['required', 'integer'], 'direction' => ['required', 'in:in,out'], 'mode' => ['required', 'in:inside,outside,poor_accuracy'], 'employee_id' => ['prohibited'], 'scenario_id' => ['prohibited']]);
        $shift = RosterShift::where('employee_id', $r->user()->employee_id)->findOrFail($d['roster_shift_id']);
        $site = $shift->snapshot['location'];
        $result = $s->clock($r->user(), $d['direction'], $d['idempotency_key'], ['roster_shift_id' => $shift->id, 'latitude' => (float) $site['latitude'] + ($d['mode'] === 'outside' ? 1 : 0), 'longitude' => $site['longitude'], 'accuracy_m' => $d['mode'] === 'poor_accuracy' ? $site['max_accuracy_m'] + 1000 : 1]);

        return response()->json($result, isset($result['rejected']) ? 422 : 200);
    }
}
