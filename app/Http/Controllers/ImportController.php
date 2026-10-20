<?php

namespace App\Http\Controllers;

use App\Domain\Imports\ImportService;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function upload(OperationalRequest $r, ImportService $s)
    {
        $r->validate(['kind' => ['required', 'string'], 'file' => ['required', 'file', 'max:10240']]);

        return response()->json($s->upload($r->user(), $r->input('kind'), $r->file('file'), $r->key()), 201);
    }

    public function show(Request $r, int $id, ImportService $s)
    {
        return response()->json($s->show($r->user(), $id))->header('Cache-Control', 'no-store, private');
    }

    public function map(OperationalRequest $r, int $id, ImportService $s)
    {
        $r->validate(['mapping' => ['required', 'array']]);

        return response()->json($s->map($r->user(), $id, $r->version(), $r->key(), $r->input('mapping')));
    }

    public function preview(OperationalRequest $r, int $id, ImportService $s)
    {
        return response()->json($s->preview($r->user(), $id, $r->version(), $r->key()));
    }

    public function commit(OperationalRequest $r, int $id, ImportService $s)
    {
        $r->validate(['checksum' => ['required', 'string', 'size:64'], 'background' => ['sometimes', 'boolean']]);
        $result = $r->boolean('background') ? $s->queue($r->user(), $id, $r->version(), $r->key(), $r->input('checksum')) : $s->commit($r->user(), $id, $r->version(), $r->key(), $r->input('checksum'));

        return response()->json($result, $result['http_status'] ?? ($r->boolean('background') ? 202 : 200));
    }
}
