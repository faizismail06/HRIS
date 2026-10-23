<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Access;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Operations\RuntimeHealth;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsHealthController extends Controller
{
    public function index(Request $r)
    {
        abort_unless(Access::companyPermission($r->user(), 'operations.manage'), 403);

        return response()->json(['services' => RuntimeHealth::status(), 'deliveries' => DB::table('notification_deliveries')->selectRaw('status,count(*) AS count')->groupBy('status')->get(), 'failed_queue_jobs' => DB::table('failed_jobs')->count(), 'pending_queue_jobs' => DB::table('jobs')->count(), 'blocked_requests' => DB::table('workflow_requests')->where('status', 'blocked')->count(), 'oldest_pending_outbox' => DB::table('notification_deliveries')->whereIn('status', ['pending', 'failed'])->min('created_at')]);
    }

    public function retry(OperationalRequest $r, string $id)
    {
        $data = $r->validate(['reason' => ['required', 'string', 'max:1000']]);

        return response()->json(Mutation::run($r->user(), 'operations.delivery.retry', $r->key(), ['id' => (int) $id] + $data, fn ($u) => abort_unless(Access::companyPermission($u, 'operations.manage'), 403), function ($u) use ($id) {
            $record = DB::table('notification_deliveries')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'failed', 409);
            DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'lease_until' => null, 'failure_code' => null, 'updated_at' => now()]);
            Audit::record($u->id, 'notification.retry_requested', 'notification_delivery', $record->id, ['prior_attempts' => $record->attempts, 'reason_provided' => true]);

            return ['id' => $record->id, 'status' => 'pending'];
        }));
    }
}
