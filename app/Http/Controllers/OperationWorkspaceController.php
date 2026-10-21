<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\SensitiveProfiles;
use App\Http\Controllers\Concerns\HandlesWorkspaceWrites;
use App\Http\Requests\OperationalRequest;
use App\Support\OperationFields;
use App\Support\WorkspaceOperations;
use App\Support\WorkspaceOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

class OperationWorkspaceController extends Controller
{
    use HandlesWorkspaceWrites;

    public function index(Request $r)
    {
        $operations = array_filter(WorkspaceOperations::all(), fn ($op) => WorkspaceOperations::allowed($r->user(), $op['gate']));
        foreach ($operations as $key => $op) {
            if (str_starts_with($key, 'export-') && $r->user()->grants()->where('role', 'auditor')->exists() && ! $r->user()->auditor_export_enabled) {
                unset($operations[$key]);

                continue;
            }
            if (str_starts_with($key, 'import-template-') && ! WorkspaceOptions::importAllowed($r->user(), substr($key, 16))) {
                unset($operations[$key]);
            }
        }

        return view('workspace.operations.index', ['operations' => collect($operations)->groupBy('module')]);
    }

    public function options(Request $r, string $source, WorkspaceOptions $options)
    {
        return response()->json($options->page($r, $source))->header('Cache-Control', 'no-store, private');
    }

    public function profileVersion(Request $r, SensitiveProfiles $profiles)
    {
        $d = $r->validate(['employee_id' => ['required', 'integer'], 'category' => ['required', 'string']]);
        $profile = $profiles->read($r->user(), (int) $d['employee_id'], $d['category']);

        return response()->json(['version' => $profile['version']])->header('Cache-Control', 'no-store, private');
    }

    public function form(Request $r, string $operation)
    {
        $spec = $this->spec($r, $operation);
        $values = session()->getOldInput() ?: Arr::except($r->input(), ['_token', 'idempotency_key']);
        $result = null;
        $resultStatus = 200;
        if ($spec['method'] === 'GET' && $r->boolean('run')) {
            $response = $this->dispatch($r, $spec);
            if (! $response instanceof JsonResponse) {
                return $response;
            }
            $result = $response->getData(true);
            $resultStatus = $response->getStatusCode();
        }

        return response()->view('workspace.operations.form', compact('spec', 'result', 'values', 'resultStatus'), $resultStatus >= 400 ? $resultStatus : 200);
    }

    public function store(OperationalRequest $r, string $operation)
    {
        $spec = $this->spec($r, $operation);
        abort_unless($spec['method'] === 'POST', 405);
        $values = Arr::except($r->input(), ['_token', 'idempotency_key']);

        return $this->workspaceWrite(function () use ($r, $spec, $values) {
            $response = $this->dispatch($r, $spec);
            if (! $response instanceof JsonResponse) {
                return $response;
            }
            $result = $response->getData(true);
            if ($spec['workflow']) {
                return to_route('workspace.requests.show', $result['id'])->with('status', 'Draf disimpan. Periksa data dan unggah bukti yang diwajibkan sebelum dikirim.');
            }

            $resultStatus = $response->getStatusCode();

            return response()->view('workspace.operations.form', compact('spec', 'result', 'values', 'resultStatus'), $resultStatus >= 400 ? $resultStatus : 200);
        });
    }

    private function spec(Request $r, string $operation): array
    {
        $spec = WorkspaceOperations::all()[$operation] ?? abort(404);
        abort_unless(WorkspaceOperations::allowed($r->user(), $spec['gate']), 403);

        return $spec;
    }

    /** Dispatch a server-owned route to its existing domain adapter, never a supplied URL. */
    private function dispatch(Request $r, array $spec)
    {
        $r->validate(['form_timezone' => ['nullable', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura,UTC']]);
        $input = OperationFields::normalize($spec['fields'], $r->all(), $r->input('form_timezone', 'Asia/Jakarta'));
        $endpoint = $spec['endpoint'];
        if (str_contains($endpoint, '{record}')) {
            if (! isset($input['record_id']) || ! ctype_digit((string) $input['record_id']) || (int) $input['record_id'] < 1) {
                throw ValidationException::withMessages(['record_id' => 'Pilih catatan yang akan diproses.']);
            }
            $endpoint = str_replace('{record}', (string) $input['record_id'], $endpoint);
        }
        unset($input['record_id']);
        if ($spec['method'] === 'GET' && $r->has('page')) {
            $r->validate(['page' => ['integer', 'min:1', 'max:100000']]);
            $input['page'] = $r->integer('page');
        }
        if ($spec['workflow']) {
            $input = ['kind' => $spec['workflow'], 'payload' => $input];
        }
        if ($r instanceof OperationalRequest) {
            $input['idempotency_key'] = $r->key();
        }
        $route = Route::getRoutes()->match(Request::create('/app/api'.$endpoint, $spec['method']));
        [$class, $method] = explode('@', $route->getActionName());
        $callRequest = clone $r;
        $callRequest->replace($input);
        $parameters = $route->parameters();
        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                $parameters[$parameter->getName()] = $callRequest;
            }
        }

        return app()->call([app($class), $method], $parameters);
    }
}
