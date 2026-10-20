<?php

namespace App\Http\Controllers;

use App\Support\PrototypeWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeWorkspaceController extends Controller
{
    private function notificationFilters(Request $request): array
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', Rule::in(array_keys(PrototypeWorkspace::readStates()))], 'delivery' => ['nullable', Rule::in(array_keys(PrototypeWorkspace::deliveries()))]]);

        return array_merge(['q' => '', 'state' => '', 'delivery' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
    }

    public function notifications(Request $request): View
    {
        $filters = $this->notificationFilters($request);
        $items = collect(PrototypeWorkspace::notifications())->sortByDesc('at')->filter(fn ($item) => (! $filters['q'] || str_contains(mb_strtolower($item['id'].' '.$item['title'].' '.$item['body']), mb_strtolower($filters['q']))) &&
            (! $filters['state'] || $filters['state'] === $item['state']) && (! $filters['delivery'] || $filters['delivery'] === $item['delivery'])
        );

        return view('workspace.notifications', compact('filters', 'items') + ['states' => PrototypeWorkspace::readStates(), 'deliveries' => PrototypeWorkspace::deliveries()]);
    }

    public function notification(Request $request, string $notification): View
    {
        $item = PrototypeWorkspace::notification($notification);
        abort_unless($item, 404);

        return view('workspace.notification', compact('item') + ['filters' => $this->notificationFilters($request)]);
    }

    public function search(Request $request): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:100'], 'category' => ['nullable', Rule::in(array_keys(PrototypeWorkspace::categories()))]]);
        $filters = array_merge(['q' => '', 'category' => ''], array_filter($input, fn ($value) => $value !== null && $value !== ''));
        $items = $filters['q'] === '' ? collect() : collect(PrototypeWorkspace::entries())->filter(fn ($item) => (! $filters['category'] || $item['category'] === $filters['category']) &&
            str_contains(mb_strtolower($item['id'].' '.$item['title'].' '.$item['context'].' '.$item['keywords']), mb_strtolower($filters['q']))
        );

        return view('workspace.search', compact('filters', 'items') + ['categories' => PrototypeWorkspace::categories()]);
    }
}
