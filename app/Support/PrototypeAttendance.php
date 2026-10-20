<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Synthetic UI scenarios, never operational attendance or geolocation evidence. */
class PrototypeAttendance
{
    public const SNAPSHOT = '2026-10-20 08:45:00';

    public static function employeeIds(): array
    {
        return ['EMP-004', 'EMP-005', 'EMP-007', 'EMP-009', 'EMP-010'];
    }

    public static function statuses(): array
    {
        return ['PRESENT' => 'Hadir', 'IN_PROGRESS' => 'Sedang bekerja', 'INCOMPLETE' => 'Belum lengkap', 'ABSENT' => 'Tidak hadir', 'LEAVE' => 'Cuti disetujui', 'DAY_OFF' => 'Libur terjadwal', 'SCHEDULED' => 'Belum mulai'];
    }

    public static function employees(): array
    {
        return collect(PrototypePeople::employees())->whereIn('id', self::employeeIds())->values()->all();
    }

    public static function records(): array
    {
        $records = [];
        $snapshot = CarbonImmutable::parse(self::SNAPSHOT, 'Asia/Jakarta');
        $approvedLeave = PrototypeRequests::approvedLeaveDates();
        foreach (self::employees() as $person) {
            for ($day = 12; $day <= 25; $day++) {
                $date = sprintf('2026-10-%02d', $day);
                $workDate = CarbonImmutable::parse($date, 'Asia/Jakarta');
                [$name, $startTime, $endTime, $breakTime, $overnight] = match ($person['id']) {
                    'EMP-004' => ['Malam', '22:00', '06:00', '01:00', true],
                    'EMP-007' => ['Pagi', '08:00', '16:00', '12:00', false],
                    'EMP-009' => ['Siang', '14:00', '22:00', '18:00', false],
                    default => ['Kantor', '09:00', '18:00', '12:00', false],
                };
                $off = match ($person['id']) {
                    'EMP-004' => in_array($day, [13, 20]),
                    'EMP-007' => in_array($day, [12, 19]),
                    'EMP-009' => in_array($day, [14, 21]),
                    default => in_array($day, [17, 18, 24, 25]),
                };
                $start = $workDate->setTimeFromTimeString($startTime);
                $end = $workDate->addDays($overnight ? 1 : 0)->setTimeFromTimeString($endTime);
                $breakStart = $workDate->addDays($overnight ? 1 : 0)->setTimeFromTimeString($breakTime);
                $status = $off ? 'DAY_OFF' : ($start->isAfter($snapshot) ? 'SCHEDULED' : ($end->isAfter($snapshot) ? 'IN_PROGRESS' : 'PRESENT'));
                if (! $off && isset($approvedLeave[$person['id']][$date])) {
                    $status = 'LEAVE';
                }
                if (! $off && (($person['id'] === 'EMP-004' && $day === 18) || ($person['id'] === 'EMP-009' && $day === 18))) {
                    $status = 'INCOMPLETE';
                }
                if ($person['id'] === 'EMP-007' && in_array($day, [15, 16])) {
                    $status = 'ABSENT';
                }

                $late = $person['id'] === 'EMP-004' && $day === 19 ? 12 : ($status === 'IN_PROGRESS' ? 5 : 0);
                $early = $person['id'] === 'EMP-004' && $day === 19 ? 10 : 0;
                $in = in_array($status, ['PRESENT', 'IN_PROGRESS', 'INCOMPLETE']) ? $start->addMinutes($late) : null;
                $out = $status === 'PRESENT' ? $end->subMinutes($early) : null;
                $events = [];
                if ($in) {
                    $events[] = ['time' => $in, 'title' => 'Clock-in contoh diterima', 'note' => 'Skenario dalam radius; bukan verifikasi GPS nyata.', 'accepted' => true];
                }
                if ($out) {
                    $events[] = ['time' => $out, 'title' => 'Clock-out contoh diterima', 'note' => 'Raw event sintetis; tidak ada unggahan foto.', 'accepted' => true];
                }
                if ($person['id'] === 'EMP-007' && $day === 15) {
                    $events[] = ['time' => $start->addMinute(), 'title' => 'Percobaan clock-in ditolak', 'note' => 'Skenario jarak 180 m dari radius contoh 100 m; akurasi contoh 18 m. Tidak dihitung hadir.', 'accepted' => false];
                }

                $records[] = [
                    'id' => 'ATT-'.$person['id'].'-'.$day, 'person' => $person, 'date' => $date,
                    'date_label' => $workDate->locale('id')->translatedFormat('l, j F Y'),
                    'shift' => $name, 'off' => $off, 'start' => $off ? null : $start, 'end' => $off ? null : $end,
                    'break_start' => $off ? null : $breakStart, 'break_end' => $off ? null : $breakStart->addHour(),
                    'overnight' => ! $off && $overnight, 'status' => $status, 'status_label' => self::statuses()[$status],
                    'in' => $in, 'out' => $out, 'late' => $late, 'early' => $early, 'events' => $events,
                    'minutes' => $in && $out ? (int) $in->diffInMinutes($out) - 60 : null,
                    'correction_until' => $workDate->addDays(7)->locale('id')->translatedFormat('j F Y'),
                ];
            }
        }

        return $records;
    }

    public static function find(string $id): ?array
    {
        return collect(self::records())->firstWhere('id', $id);
    }

    public static function time(?CarbonImmutable $time): string
    {
        return $time ? $time->locale('id')->translatedFormat('j M, H.i') : '—';
    }
}
