<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Read-only workflow/leave illustrations; quantities are integer half-day units. */
class PrototypeRequests
{
    public const SNAPSHOT = '2026-10-20 08:45:00';

    public static function types(): array
    {
        return ['LEAVE' => 'Cuti', 'CORRECTION' => 'Koreksi absensi', 'OVERTIME' => 'Lembur', 'SWAP' => 'Tukar shift'];
    }

    public static function statuses(): array
    {
        return ['SUBMITTED' => 'Diajukan', 'IN_REVIEW' => 'Dalam tinjauan', 'APPROVED' => 'Disetujui', 'REJECTED' => 'Ditolak', 'WITHDRAWN' => 'Ditarik'];
    }

    public static function employees(): array
    {
        return collect(PrototypePeople::employees())->whereIn('id', ['EMP-004', 'EMP-005', 'EMP-007', 'EMP-009', 'EMP-010'])->values()->all();
    }

    private static function step(string $role, string $actor, string $state, ?string $at = null): array
    {
        return compact('role', 'actor', 'state', 'at');
    }

    public static function all(): array
    {
        $rows = [
            ['id' => 'CUT-041', 'type' => 'LEAVE', 'employee' => 'EMP-004', 'status' => 'IN_REVIEW', 'dates' => ['2026-10-21', '2026-10-22'], 'period' => '21–22 Oktober 2026', 'units' => 4, 'submitted' => '2026-10-19 10:00', 'deadline' => '2026-10-21 15:00', 'summary' => 'Dua hari kerja sesuai roster malam. Saldo ditahan sebagai reservasi, belum menjadi debit final.', 'steps' => [self::step('Manajer', 'Bima Prasetya', 'DONE', '2026-10-19 15:00'), self::step('HR', 'Lestari Anindya', 'ACTIVE')]],
            ['id' => 'CUT-040', 'type' => 'LEAVE', 'employee' => 'EMP-004', 'status' => 'APPROVED', 'dates' => ['2026-10-23'], 'period' => '23 Oktober 2026', 'units' => 2, 'submitted' => '2026-10-15 09:00', 'deadline' => null, 'summary' => 'Satu hari kerja contoh disetujui. Reservasi telah dikonversi menjadi debit, tanpa pemotongan kedua.', 'steps' => [self::step('Manajer', 'Bima Prasetya', 'DONE', '2026-10-15 11:00'), self::step('HR', 'Lestari Anindya', 'DONE', '2026-10-15 14:00')]],
            ['id' => 'CUT-014', 'type' => 'LEAVE', 'employee' => 'EMP-010', 'status' => 'APPROVED', 'dates' => ['2026-10-19'], 'period' => '19 Oktober 2026', 'units' => 2, 'submitted' => '2026-10-16 09:00', 'deadline' => null, 'summary' => 'Satu hari kerja kantor disetujui melalui workflow contoh satu tahap. Tidak ada alasan pribadi atau lampiran medis.', 'steps' => [self::step('Manajer', 'Lestari Anindya', 'DONE', '2026-10-16 11:00')]],
            ['id' => 'CUT-042', 'type' => 'LEAVE', 'employee' => 'EMP-005', 'status' => 'SUBMITTED', 'dates' => ['2026-10-22'], 'period' => '22 Oktober 2026 · paruh pertama', 'units' => 1, 'submitted' => '2026-10-20 08:00', 'deadline' => '2026-10-21 08:00', 'summary' => 'Setengah hari contoh 09.00–14.00 WIB, dikurangi istirahat 12.00–13.00: empat jam kerja. Pengajuan tertunda tidak mengubah ketersediaan final.', 'steps' => [self::step('Manajer', 'Ratna Putri', 'ACTIVE'), self::step('HR', 'Lestari Anindya', 'WAITING')]],
            ['id' => 'CUT-039', 'type' => 'LEAVE', 'employee' => 'EMP-007', 'status' => 'REJECTED', 'dates' => ['2026-10-24', '2026-10-25'], 'period' => '24–25 Oktober 2026', 'units' => 4, 'submitted' => '2026-10-17 09:00', 'deadline' => null, 'summary' => 'Sabtu dan Minggu adalah hari kerja pada roster contoh ini. Reservasi dilepas saat ditolak.', 'decision' => 'Cakupan tim minimum belum terpenuhi dalam skenario contoh.', 'steps' => [self::step('Manajer', 'Bima Prasetya', 'REJECTED', '2026-10-19 16:00'), self::step('HR', 'Lestari Anindya', 'CANCELLED')]],
            ['id' => 'COR-026', 'type' => 'CORRECTION', 'employee' => 'EMP-004', 'status' => 'IN_REVIEW', 'dates' => ['2026-10-18'], 'period' => 'Shift malam 18–19 Oktober 2026', 'units' => null, 'submitted' => '2026-10-19 08:30', 'deadline' => '2026-10-20 14:00', 'summary' => 'Usulan clock-out 19 Oktober pukul 06.02 WIB untuk shift yang belum lengkap. Raw event belum berubah karena keputusan final belum ada.', 'source' => 'ATT-EMP-004-18', 'steps' => [self::step('Manajer', 'Bima Prasetya', 'DONE', '2026-10-19 14:00'), self::step('HR', 'Lestari Anindya', 'ACTIVE')]],
            ['id' => 'OVT-007', 'type' => 'OVERTIME', 'employee' => 'EMP-007', 'status' => 'SUBMITTED', 'dates' => ['2026-10-24', '2026-10-25'], 'period' => '24 Oktober 20.00 – 25 Oktober 00.00 WIB', 'units' => null, 'submitted' => '2026-10-20 08:10', 'deadline' => '2026-10-21 08:10', 'summary' => 'Rencana empat jam melewati tengah malam. Belum ada konfirmasi kerja aktual; rencana dan persetujuan tidak otomatis menjadi lembur terbayar.', 'source' => 'ATT-EMP-007-24', 'steps' => [self::step('Manajer', 'Bima Prasetya', 'ACTIVE')]],
            ['id' => 'SWP-003', 'type' => 'SWAP', 'employee' => 'EMP-009', 'status' => 'WITHDRAWN', 'dates' => ['2026-10-22'], 'period' => '22 Oktober 2026 · 14.00–22.00 WIB', 'units' => null, 'submitted' => '2026-10-19 13:00', 'deadline' => null, 'summary' => 'Permintaan ditarik Raka pada 20 Oktober pukul 08.15 WIB sebelum persetujuan rekan dan manajer. Roster asal tetap utuh.', 'decision' => 'Rencana pertukaran dibatalkan pemohon.', 'source' => 'ATT-EMP-009-22', 'steps' => [self::step('Persetujuan rekan', 'Dimas Saputra', 'CANCELLED'), self::step('Manajer', 'Bima Prasetya', 'CANCELLED')]],
        ];

        return array_map(function ($row) {
            $row['person'] = PrototypePeople::find($row['employee']);
            $row['type_label'] = self::types()[$row['type']];
            $row['status_label'] = self::statuses()[$row['status']];
            $row['active_step'] = collect($row['steps'])->firstWhere('state', 'ACTIVE');
            $minutes = (int) CarbonImmutable::parse($row['submitted'], 'Asia/Jakarta')->diffInMinutes(CarbonImmutable::parse(self::SNAPSHOT, 'Asia/Jakarta'));
            $row['age'] = intdiv($minutes, 60).' jam '.($minutes % 60).' menit';

            return $row;
        }, $rows);
    }

    public static function find(string $id): ?array
    {
        return collect(self::all())->firstWhere('id', $id);
    }

    public static function approvedLeaveDates(): array
    {
        $dates = [];
        foreach (self::all() as $item) {
            if ($item['type'] === 'LEAVE' && $item['status'] === 'APPROVED') {
                foreach ($item['dates'] as $date) {
                    $dates[$item['employee']][$date] = true;
                }
            }
        }

        return $dates;
    }

    public static function days(int $units): string
    {
        return $units % 2 === 0 ? (string) intdiv($units, 2) : number_format($units / 2, 1, ',', '.');
    }

    public static function ledger(string $employee): array
    {
        $rows = [['at' => '2026-01-01', 'kind' => 'GRANT', 'units' => 24, 'source' => 'Grant 2026 contoh']];
        $extra = match ($employee) {
            'EMP-004' => [['2026-01-26', 'DEBIT', 2, 'Pemakaian Januari contoh'], ['2026-07-14', 'DEBIT', 2, 'Pemakaian Juli contoh'], ['2026-10-15', 'DEBIT', 2, 'CUT-040'], ['2026-10-19', 'RESERVE', 4, 'CUT-041']],
            'EMP-005' => [['2026-08-01', 'DEBIT', 4, 'Pemakaian terdahulu contoh'], ['2026-10-20', 'RESERVE', 1, 'CUT-042']],
            'EMP-007' => [['2026-08-01', 'DEBIT', 6, 'Pemakaian terdahulu contoh'], ['2026-10-17', 'RESERVE', 4, 'CUT-039'], ['2026-10-19', 'RELEASE', 4, 'CUT-039']],
            'EMP-009' => [['2026-08-01', 'DEBIT', 18, 'Pemakaian terdahulu contoh']],
            default => [['2026-10-16', 'DEBIT', 2, 'CUT-014']],
        };
        foreach ($extra as [$at, $kind, $units, $source]) {
            $rows[] = compact('at', 'kind', 'units', 'source');
        }
        $entries = collect($rows);
        $credits = $entries->where('kind', 'GRANT')->sum('units');
        $used = $entries->where('kind', 'DEBIT')->sum('units');
        $reserved = $entries->where('kind', 'RESERVE')->sum('units') - $entries->where('kind', 'RELEASE')->sum('units');

        return compact('rows', 'credits', 'used', 'reserved') + ['available' => $credits - $used - $reserved];
    }
}
