<?php

namespace App\Support;

/** Read-only report projections and archived example snapshots. */
class PrototypeReports
{
    public static function summary(array $records): array
    {
        $counts = array_fill_keys(array_keys(PrototypeAttendance::statuses()), 0);
        $late = $early = 0;
        foreach ($records as $record) {
            $counts[$record['status']]++;
            if ($record['status'] === 'PRESENT') {
                $late += $record['late'] > 0 ? 1 : 0;
                $early += $record['early'] > 0 ? 1 : 0;
            }
        }

        return ['counts' => $counts, 'late' => $late, 'early' => $early, 'total' => count($records)];
    }

    public static function periods(): array
    {
        $snapshot = [
            ['employee' => 'EMP-004', 'name' => 'Nadia Pramesti', 'branch' => 'Jakarta', 'job' => 'Koordinator Operasional', 'present' => 3, 'late' => 1, 'early' => 1, 'source' => 'SRC-SEP-004-28-30'],
            ['employee' => 'EMP-005', 'name' => 'Arif Wicaksono', 'branch' => 'Bandung', 'job' => 'Analis Keuangan', 'present' => 3, 'late' => 0, 'early' => 0, 'source' => 'SRC-SEP-005-28-30'],
            ['employee' => 'EMP-011', 'name' => 'Intan Kusuma', 'branch' => 'Bandung', 'job' => 'Staf Operasional', 'present' => 3, 'late' => 0, 'early' => 0, 'source' => 'SRC-SEP-011-28-30'],
        ];

        return [
            ['id' => 'CLOSE-OCT-26', 'title' => 'Oktober · periode berjalan', 'status' => 'OPEN', 'status_label' => 'Terbuka', 'start' => '2026-10-12', 'end' => '2026-10-25', 'source_version' => 'ATT-OCT-V2', 'review_version' => null, 'policy' => 'ATT-DEMO-V1', 'roster' => 'RST-OCT-V1', 'payroll' => null, 'snapshot' => [], 'checksum' => null, 'note' => 'Cuplikan 12–25 Oktober, bukan laporan sebulan penuh. Hari mendatang dan shift berjalan belum dapat dinyatakan final.', 'history' => [['date' => '2026-10-12', 'actor' => 'Petugas HR contoh', 'event' => 'Periode contoh dibuka, belum ditinjau.']]],
            ['id' => 'CLOSE-OCT-W1', 'title' => '12–18 Oktober · tinjauan', 'status' => 'REVIEW', 'status_label' => 'Dalam tinjauan', 'start' => '2026-10-12', 'end' => '2026-10-18', 'source_version' => 'ATT-OCT-W1-V2', 'review_version' => 'ATT-OCT-W1-V1', 'policy' => 'ATT-DEMO-V1', 'roster' => 'RST-OCT-V1', 'payroll' => null, 'snapshot' => [], 'checksum' => null, 'note' => 'Sumber berubah setelah tinjauan. Kesiapan versi V1 tidak berlaku untuk V2, dan punch belum lengkap masih menghambat penutupan.', 'history' => [['date' => '2026-10-19', 'actor' => 'Lestari Anindya · HR', 'event' => 'Tinjauan V1 dimulai.'], ['date' => '2026-10-20', 'actor' => 'Petugas HR contoh', 'event' => 'Sumber V2 menggantikan masukan tinjauan; perlu tinjauan ulang.']]],
            ['id' => 'CLOSE-SEP-26', 'title' => 'Arsip snapshot September', 'status' => 'CLOSED', 'status_label' => 'Ditutup', 'start' => '2026-09-28', 'end' => '2026-09-30', 'source_version' => 'ATT-SEP-SNAP-V1', 'review_version' => 'ATT-SEP-SNAP-V1', 'policy' => 'ATT-DEMO-V1', 'roster' => 'ROS-SEP-DEMO-V1', 'payroll' => 'PAY-SEP-26', 'snapshot' => $snapshot, 'checksum' => hash('sha256', json_encode($snapshot)), 'note' => 'Arsip terpisah untuk tiga karyawan pada 28–30 September. Termasuk Intan selama masih bekerja; tidak diambil dari profil atau rekap Oktober. Payroll terkait sudah final, sehingga koreksi tidak menimpa snapshot ini.', 'history' => [['date' => '2026-09-30', 'actor' => 'Lestari Anindya · HR', 'event' => 'Revisi1 ditutup dengan snapshot contoh.'], ['date' => '2026-10-01', 'actor' => 'Approver payroll contoh', 'event' => 'Payroll final terkait; koreksi berikutnya perlu penyesuaian.']]],
        ];
    }

    public static function period(string $id): ?array
    {
        return collect(self::periods())->firstWhere('id', $id);
    }

    public static function checks(array $period): array
    {
        if ($period['status'] === 'CLOSED') {
            return [['level' => 'INFO', 'title' => 'Snapshot arsip dipertahankan', 'detail' => 'Setelah payroll final, koreksi mencatat periode asal dan periode penyesuaian terpisah.']];
        }
        $records = collect(PrototypeAttendance::records())->whereBetween('date', [$period['start'], $period['end']]);
        $checks = [];
        if ($period['review_version'] !== $period['source_version']) {
            $checks[] = ['level' => 'BLOCKER', 'title' => 'Versi sumber belum lolos tinjauan', 'detail' => 'Tinjauan harus mengacu ke versi sumber terkini.'];
        }
        $incomplete = $records->where('status', 'INCOMPLETE')->count();
        if ($incomplete) {
            $checks[] = ['level' => 'BLOCKER', 'title' => $incomplete.' shift belum lengkap', 'detail' => 'Punch Nadia dan Raka 18 Oktober perlu rekonsiliasi; contoh koreksi belum final.'];
        }
        if ($records->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])->isNotEmpty()) {
            $checks[] = ['level' => 'BLOCKER', 'title' => 'Shift berjalan atau mendatang', 'detail' => 'Tidak boleh menutup hasil yang belum selesai pada snapshot.'];
        }
        $checks[] = ['level' => 'WARNING', 'title' => 'Ketidakhadiran perlu ditinjau', 'detail' => 'Dimas 15–16 Oktober tidak hadir; percobaan lokasi ditolak bukan bukti hadir.'];

        return $checks;
    }
}
