<?php

namespace App\Http\Controllers;

use App\Support\PrototypePeople;
use App\Support\PrototypeScreens;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrototypeScreensController extends Controller
{
    public function index(): View
    {
        return view('preview.catalog', ['groups' => collect(PrototypeScreens::forms())->groupBy('group', preserveKeys: true), 'states' => PrototypeScreens::states(), 'personas' => PrototypePortalController::PERSONAS]);
    }

    public function form(Request $request, string $screen): View
    {
        $spec = PrototypeScreens::forms()[$screen] ?? null;
        abort_unless($spec, 404);
        $input = $request->validate(['state' => ['nullable', Rule::in(['ready', 'invalid'])]]);

        return view('preview.form', compact('screen', 'spec') + ['state' => $input['state'] ?? 'ready']);
    }

    public function state(string $state): View
    {
        $spec = PrototypeScreens::states()[$state] ?? null;
        abort_unless($spec, 404);
        $retained = in_array($state, ['error', 'offline']) ? array_map(fn ($field) => [
            'label' => $field[0],
            'value' => match ($field[2] ?? 'text') {
                'date' => PrototypePeople::date($field[1]),
                'datetime-local' => CarbonImmutable::parse($field[1])->locale('id')->translatedFormat('j F Y, H.i').' WIB',
                default => $field[1],
            },
        ], array_slice(PrototypeScreens::forms()[$spec['screen']]['fields'], 0, 3)) : [];

        return view('preview.state', compact('state', 'spec', 'retained'));
    }
}
