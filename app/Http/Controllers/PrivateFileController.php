<?php

namespace App\Http\Controllers;

use App\Domain\Operations\PrivateFiles;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;

class PrivateFileController extends Controller
{
    public function store(OperationalRequest $r, string $id, PrivateFiles $files)
    {
        $r->validate(['file' => ['required', 'file']]);

        return response()->json($files->upload($r->user(), (int) $id, $r->file('file'), $r->key()), 201);
    }

    public function download(Request $r, string $id, PrivateFiles $files)
    {
        return $files->download($r->user(), (int) $id);
    }
}
