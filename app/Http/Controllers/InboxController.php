<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(DB::table('inbox_messages')->join('outbox_events', 'outbox_events.id', '=', 'inbox_messages.outbox_event_id')->where('inbox_messages.user_id', $r->user()->id)->whereNull('archived_at')->orderByDesc('inbox_messages.id')->select(['inbox_messages.id', 'outbox_events.event', 'outbox_events.subject_type', 'outbox_events.subject_id', 'inbox_messages.read_at', 'inbox_messages.created_at'])->paginate(20));
    }

    public function update(Request $r, string $id)
    {
        $data = $r->validate(['action' => ['required', 'in:read,archive']]);
        $record = DB::table('inbox_messages')->where('user_id', $r->user()->id)->where('id', $id)->firstOrFail();
        DB::table('inbox_messages')->where('id', $record->id)->where('user_id', $r->user()->id)->update([$data['action'] === 'read' ? 'read_at' : 'archived_at' => now()]);

        return response()->json(['id' => $record->id, 'status' => $data['action']]);
    }
}
