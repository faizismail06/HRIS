<?php

namespace App\Domain\Operations;

use App\Domain\Identity\Audit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationPreferences
{
    public function update(User $actor, string $key, array $input): array
    {
        $d = Validator::make($input, ['email_enabled' => ['required', 'boolean'], 'in_app_enabled' => ['required', 'boolean'], 'timezone' => ['required', 'timezone'], 'delivery_mode' => ['required', 'in:immediate,daily'], 'digest_time' => ['required', 'date_format:H:i'], 'quiet_start' => ['nullable', 'required_with:quiet_end', 'date_format:H:i'], 'quiet_end' => ['nullable', 'required_with:quiet_start', 'date_format:H:i', 'different:quiet_start']])->validate();

        return Mutation::run($actor, 'notifications.preferences', $key, $d, fn ($u) => null, function ($u) use ($d) {
            DB::table('notification_preferences')->updateOrInsert(['user_id' => $u->id], $d + ['created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'notification.preferences_changed', 'user', $u->id, ['email_enabled' => $d['email_enabled'], 'in_app_enabled' => $d['in_app_enabled'], 'delivery_mode' => $d['delivery_mode']]);

            return ['user_id' => $u->id, 'updated' => true];
        });
    }

    public static function get(int $user): object
    {
        return DB::table('notification_preferences')->where('user_id', $user)->first() ?? (object) ['email_enabled' => true, 'in_app_enabled' => true, 'timezone' => config('app.timezone'), 'delivery_mode' => 'immediate', 'digest_time' => '09:00', 'quiet_start' => null, 'quiet_end' => null];
    }

    public static function due(CarbonImmutable $createdAt, object $p, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $at = $now->setTimezone($p->timezone);
        if ($p->delivery_mode === 'daily') {
            $created = $createdAt->setTimezone($p->timezone);
            $nominal = CarbonImmutable::parse($created->toDateString().' '.$p->digest_time, $p->timezone);
            if ($nominal <= $created) {
                $nominal = $nominal->addDay();
            }
            if ($nominal > $at) {
                $at = $nominal;
            }
        }
        if ($p->quiet_start && $p->quiet_end) {
            $time = $at->format('H:i');
            $overnight = $p->quiet_start > $p->quiet_end;
            $quiet = $overnight ? ($time >= $p->quiet_start || $time < $p->quiet_end) : ($time >= $p->quiet_start && $time < $p->quiet_end);
            if ($quiet) {
                $end = CarbonImmutable::parse($at->toDateString().' '.$p->quiet_end, $p->timezone);
                if ($overnight && $time >= $p->quiet_start) {
                    $end = $end->addDay();
                }
                $at = $end;
            }
        }

        return $at;
    }
}
