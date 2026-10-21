<?php

namespace App\Domain\Operations;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationDelivery
{
    /** True means skipped, deferred, already leased or handled as a digest; false proceeds with immediate delivery. */
    public function handlePreferences(int $id): bool
    {
        $d = DB::table('notification_deliveries')->find($id);
        if (! $d || in_array($d->status, ['sent', 'skipped']) || $d->attempts >= 5) {
            return true;
        }
        $event = DB::table('outbox_events')->find($d->outbox_event_id);
        $user = $event ? User::find($event->user_id) : null;
        $p = $user ? NotificationPreferences::get($user->id) : null;
        if (! $user || ! $p->email_enabled || ! NotificationAccess::visible($user, $event)) {
            DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'skipped', 'lease_until' => null, 'updated_at' => now()]);

            return true;
        }
        $at = NotificationPreferences::due(CarbonImmutable::parse($event->created_at), $p);
        if ($event->subject_type === 'payroll_row') {
            $release = DB::table('payroll_rows')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->where('payroll_rows.id', $event->subject_id)->value('release_at');
            if ($release && CarbonImmutable::parse($release) > $at) {
                $at = CarbonImmutable::parse($release);
            }
        }
        if ($at->isFuture()) {
            DB::table('notification_deliveries')->where('id', $id)->update(['available_at' => $at->toIso8601String(), 'updated_at' => now()]);

            return true;
        }
        if ($p->delivery_mode !== 'daily') {
            return false;
        }
        $this->digest($user, $p);

        return true;
    }

    private function digest(User $user, object $preference): void
    {
        $date = CarbonImmutable::now($preference->timezone)->toDateString();
        $claimed = DB::transaction(function () use ($user, $preference, $date) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('notification_digest_runs')->insertOrIgnore(['user_id' => $user->id, 'local_date' => $date, 'created_at' => now(), 'updated_at' => now()]);
            $run = DB::table('notification_digest_runs')->where('user_id', $user->id)->where('local_date', $date)->lockForUpdate()->firstOrFail();
            $deliveries = DB::table('notification_deliveries')->join('outbox_events', 'outbox_events.id', '=', 'notification_deliveries.outbox_event_id')->where('outbox_events.user_id', $user->id)->whereIn('notification_deliveries.status', ['pending', 'failed'])->where('attempts', '<', 5)->where('available_at', '<=', now())->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))->orderBy('notification_deliveries.id')->limit(1000)->get(['notification_deliveries.*']);
            if ($run->status === 'sent') {
                $next = NotificationPreferences::due(CarbonImmutable::now(), $preference);
                DB::table('notification_deliveries')->whereIn('id', $deliveries->pluck('id'))->update(['available_at' => $next->toIso8601String()]);

                return null;
            }
            if ($run->lease_until && CarbonImmutable::parse($run->lease_until)->isFuture()) {
                return null;
            }
            $ids = [];
            foreach ($deliveries as $delivery) {
                $event = DB::table('outbox_events')->find($delivery->outbox_event_id);
                if (! NotificationAccess::visible($user, $event)) {
                    DB::table('notification_deliveries')->where('id', $delivery->id)->update(['status' => 'skipped']);

                    continue;
                }
                $due = NotificationPreferences::due(CarbonImmutable::parse($event->created_at), $preference);
                if ($event->subject_type === 'payroll_row') {
                    $release = DB::table('payroll_rows')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_rows.payroll_batch_id')->where('payroll_rows.id', $event->subject_id)->value('release_at');
                    if ($release && CarbonImmutable::parse($release) > $due) {
                        $due = CarbonImmutable::parse($release);
                    }
                }
                if ($due->isFuture()) {
                    DB::table('notification_deliveries')->where('id', $delivery->id)->update(['available_at' => $due->toIso8601String()]);

                    continue;
                }
                $ids[] = $delivery->id;
            }
            if (! $ids) {
                return null;
            }
            DB::table('notification_deliveries')->whereIn('id', $ids)->increment('attempts', 1, ['lease_until' => now()->addMinutes(5), 'updated_at' => now()]);
            DB::table('notification_digest_runs')->where('id', $run->id)->update(['status' => 'sending', 'lease_until' => now()->addMinutes(5), 'updated_at' => now()]);

            return ['run_id' => $run->id, 'ids' => $ids];
        });
        if (! $claimed) {
            return;
        }
        try {
            Mail::raw('Ada pembaruan tugas di HRIS. Masuk ke ruang kerja untuk memeriksa pengajuan Anda.', function ($mail) use ($user, $date) {
                $mail->to($user->email)->subject('Ringkasan ruang kerja HRIS');
                $mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'hris-digest-'.$user->id.'-'.$date.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
            });
            DB::transaction(function () use ($claimed) {
                DB::table('notification_deliveries')->whereIn('id', $claimed['ids'])->update(['status' => 'sent', 'sent_at' => now(), 'lease_until' => null, 'failure_code' => null, 'updated_at' => now()]);
                DB::table('notification_digest_runs')->where('id', $claimed['run_id'])->update(['status' => 'sent', 'lease_until' => null, 'updated_at' => now()]);
            });
        } catch (\Throwable $error) {
            DB::table('notification_deliveries')->whereIn('id', $claimed['ids'])->update(['status' => 'failed', 'available_at' => now()->addMinutes(5), 'lease_until' => null, 'failure_code' => class_basename($error), 'updated_at' => now()]);
            DB::table('notification_digest_runs')->where('id', $claimed['run_id'])->update(['status' => 'failed', 'lease_until' => null, 'updated_at' => now()]);
            throw new \RuntimeException('HRIS digest delivery failed; inspect delivery status.');
        }
    }
}
