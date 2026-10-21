<?php

namespace App\Domain\Workflow;

use App\Models\User;
use App\Models\WorkflowRequest;

class WorkflowListing
{
    public function page(User $user, ?string $status = null, int $cursor = 0): array
    {
        $query = WorkflowRequest::where('id', '>', $cursor)->orderBy('id');
        if ($status) {
            $query->where('status', $status);
        }
        $rows = [];
        $scanned = 0;
        foreach ($query->limit(200)->get() as $record) {
            $scanned++;
            $cursor = $record->id;
            if (WorkflowAccess::visible($user, $record)) {
                $rows[] = $record;
            }
            if (count($rows) === 20) {
                break;
            }
        }

        return ['data' => $rows, 'next_cursor' => $cursor, 'has_more' => count($rows) === 20 || $scanned === 200];
    }
}
