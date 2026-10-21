<?php

namespace App\Domain\Workflow;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BusinessDeadline
{
    public function add(CarbonImmutable $start, int $minutes, array $calendar): array
    {
        $cursor = $start->setTimezone($calendar['timezone']);
        $remaining = $minutes * 60;
        $holidays = [];
        for ($i = 0; $i < 730; $i++) {
            $day = $cursor->startOfDay();
            $holiday = DB::table('work_calendar_dates')->where('branch_id', $calendar['branch_id'])->where('work_date', $day->toDateString())->orderByDesc('version')->first();
            if ($holiday) {
                $holidays[$day->toDateString()] = ['id' => $holiday->id, 'version' => $holiday->version];
            }
            if (in_array($day->isoWeekday(), $calendar['working_days'], true) && ! $holiday?->is_holiday) {
                $open = CarbonImmutable::parse($day->toDateString().' '.$calendar['start_local'], $calendar['timezone']);
                $close = CarbonImmutable::parse($day->toDateString().' '.$calendar['end_local'], $calendar['timezone']);
                $current = $cursor > $open ? $cursor : $open;
                $available = max(0, $close->getTimestamp() - $current->getTimestamp());
                if ($remaining <= $available) {
                    return ['at' => $current->addSeconds($remaining), 'holiday_versions' => $holidays];
                }
                $remaining -= $available;
            }
            $cursor = $day->addDay();
        }
        abort(422, 'Tenggat tidak dapat dicapai dalam730hari kalender kerja.');
    }
}
