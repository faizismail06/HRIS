<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait HandlesWorkspaceWrites
{
    protected function workspaceWrite(callable $callback)
    {
        try {
            return $callback();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422])) {
                throw $e;
            }

            return back()->withInput()->withErrors(['operation' => $e->getMessage() ?: 'Data belum dapat diproses. Muat ulang dan periksa isian serta sumber terbaru.']);
        }
    }
}
