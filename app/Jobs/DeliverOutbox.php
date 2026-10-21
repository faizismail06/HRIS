<?php

namespace App\Jobs;

use App\Domain\Identity\AccountSecurity;
use App\Domain\Operations\NotificationDelivery;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\User;
use App\Models\WorkflowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliverOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        if (app(NotificationDelivery::class)->handlePreferences($this->deliveryId)) {
            return;
        }
        $delivery = DB::transaction(function () {
            $d = DB::table('notification_deliveries')->where('id', $this->deliveryId)->lockForUpdate()->first();
            if (! $d || $d->status === 'sent' || $d->status === 'skipped' || CarbonImmutable::parse($d->available_at)->isFuture() || ($d->lease_until && CarbonImmutable::parse($d->lease_until)->isFuture())) {
                return null;
            }
            DB::table('notification_deliveries')->where('id', $d->id)->update(['lease_until' => now()->addMinutes(5), 'attempts' => $d->attempts + 1, 'updated_at' => now()]);

            return $d;
        });
        if (! $delivery) {
            return;
        }
        $event = DB::table('outbox_events')->find($delivery->outbox_event_id);
        $user = User::find($event->user_id);
        $request = $event->subject_type === 'workflow_request' ? WorkflowRequest::find($event->subject_id) : null;
        if (! $user || ! AccountSecurity::available($user) || ($request && ! WorkflowAccess::visible($user, $request))) {
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'skipped', 'lease_until' => null, 'updated_at' => now()]);

            return;
        }
        try {
            // No amounts, leave reasons, medical content or attachments are put in email.
            Mail::raw('Ada pembaruan di ruang kerja HRIS. Masuk untuk melihat pengajuan dan tugas Anda.', function ($mail) use ($user, $event) {
                $mail->to($user->email)->subject('Pembaruan ruang kerja HRIS');
                $mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'hris-event-'.$event->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
            });
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'sent', 'sent_at' => now(), 'lease_until' => null, 'failure_code' => null, 'updated_at' => now()]);
        } catch (\Throwable $error) {
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'failed', 'available_at' => now()->addMinutes(min(60, 2 ** min(6, $delivery->attempts + 1))), 'lease_until' => null, 'failure_code' => class_basename($error), 'updated_at' => now()]);
            // Do not serialize provider errors which might include recipients, credentials or message content.
            throw new \RuntimeException('HRIS notification delivery failed; inspect delivery status.');
        }
    }
}
