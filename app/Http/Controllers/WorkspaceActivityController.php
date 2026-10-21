<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WorkspaceActivityController extends Controller
{
    public function notifications(Request $request, InboxController $inbox)
    {
        // Presentation adapter: keep the API's bounded scan and live permission filtering.
        $page = $inbox->index($request)->getData(true);

        return view('workspace.activity.notifications', $page);
    }

    public function updateNotification(Request $request, string $id, InboxController $inbox)
    {
        $inbox->update($request, $id);

        return to_route('workspace.notifications')->with('status', $request->input('action') === 'read' ? 'Notifikasi ditandai sudah dibaca.' : 'Notifikasi diarsipkan.');
    }

    public function search(Request $request, WorkspaceSearchController $search)
    {
        $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:80'], 'as_of' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $page = $request->filled('q') ? $search($request)->getData(true) : ['data' => [], 'q' => '', 'as_of' => today()->toDateString()];

        return view('workspace.activity.search', $page);
    }
}
