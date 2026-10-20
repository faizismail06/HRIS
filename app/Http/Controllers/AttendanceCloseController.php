<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\CloseService;
use App\Domain\Attendance\OvertimeService;
use App\Http\Requests\OperationalRequest;

class AttendanceCloseController extends Controller
{
    public function create(OperationalRequest $r, CloseService $s)
    {
        return response()->json($s->create($r->user(), $r->key(), $r->except('idempotency_key')), 201);
    }

    public function review(OperationalRequest $r, string $id, CloseService $s)
    {
        return response()->json($s->review($r->user(), (int) $id, $r->version(), $r->key()));
    }

    public function close(OperationalRequest $r, string $id, CloseService $s)
    {
        $data = $r->validate(['checksum' => ['required', 'string', 'size:64']]);

        return response()->json($s->close($r->user(), (int) $id, $r->version(), $r->key(), $data['checksum']));
    }

    public function reopen(OperationalRequest $r, string $id, CloseService $s)
    {
        return response()->json($s->reopen($r->user(), (int) $id, $r->version(), $r->key(), (string) $r->input('reason')));
    }

    public function actual(OperationalRequest $r, string $id, OvertimeService $s)
    {
        return response()->json($s->actual($r->user(), (int) $id, $r->version(), $r->key(), $r->except(['idempotency_key', 'expected_version'])));
    }

    public function verify(OperationalRequest $r, string $id, OvertimeService $s)
    {
        return response()->json($s->verify($r->user(), (int) $id, $r->version(), $r->key(), $r->input('exception_reason')));
    }
}
