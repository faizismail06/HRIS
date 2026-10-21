<?php

namespace App\Domain\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class Outbox
{
    public static function notify(array $users, string $event, string $type, int $id, string $dedup): void
    {
        foreach (array_unique($users) as $user) {
            $key = hash('sha256', $dedup.'|'.$user);
            $eventId = DB::table('outbox_events')->where('dedup_key', $key)->value('id');
            if (! $eventId) {
                $eventId = DB::table('outbox_events')->insertGetId(['dedup_key' => $key, 'event' => $event, 'user_id' => $user, 'subject_type' => $type, 'subject_id' => $id, 'created_at' => now()]);
            }
            $preference = NotificationPreferences::get($user);
            if ($preference->in_app_enabled) {
                DB::table('inbox_messages')->insertOrIgnore(['outbox_event_id' => $eventId, 'user_id' => $user, 'created_at' => now()]);
            }
            DB::table('notification_deliveries')->insertOrIgnore(['outbox_event_id' => $eventId, 'channel' => 'email', 'status' => $preference->email_enabled ? 'pending' : 'skipped', 'attempts' => 0, 'available_at' => NotificationPreferences::due(CarbonImmutable::now(config('app.timezone')), $preference)->toIso8601String(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
