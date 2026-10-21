<?php

namespace App\Http\Controllers;

use App\Domain\Operations\NotificationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['before_id' => ['nullable', 'integer', 'min:1']]);
        $query = DB::table('inbox_messages')->join('outbox_events', 'outbox_events.id', '=', 'inbox_messages.outbox_event_id')->where('inbox_messages.user_id', $r->user()->id)->whereNull('archived_at')->orderByDesc('inbox_messages.id');
        if ($r->filled('before_id')) {
            $query->where('inbox_messages.id', '<', $r->integer('before_id'));
        }
        $candidates = $query->limit(200)->get(['inbox_messages.id', 'outbox_events.event', 'outbox_events.subject_type', 'outbox_events.subject_id', 'inbox_messages.read_at', 'inbox_messages.created_at']);
        $visible = $candidates->filter(fn ($event) => NotificationAccess::visible($r->user(), $event))->take(20)->values();

        return response()->json(['data' => $visible, 'next_before_id' => $visible->count() === 20 ? $visible->last()->id : $candidates->last()?->id]);
    }

    public function update(Request $r, string $id)
    {
        $data = $r->validate(['action' => ['required', 'in:read,archive']]);
        $record = DB::table('inbox_messages')->where('user_id', $r->user()->id)->where('id', $id)->firstOrFail();
        DB::table('inbox_messages')->where('id', $record->id)->where('user_id', $r->user()->id)->update([$data['action'] === 'read' ? 'read_at' : 'archived_at' => now()]);

        return response()->json(['id' => $record->id, 'status' => $data['action']]);
    }
}
