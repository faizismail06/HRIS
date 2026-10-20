<?php

namespace App\Support;

/** Immutable, synthetic lifecycle and custody examples; no account or asset operations. */
class PrototypeLifecycle
{
    public static function states(): array
    {
        return ['PREPARING' => 'Persiapan', 'IN_PROGRESS' => 'Berjalan', 'OPEN_CLEARANCE' => 'Clearance terbuka', 'COMPLETED' => 'Selesai'];
    }

    public static function cases(): array
    {
        $rows = [
            ['id' => 'LIF-001', 'employee' => 'EMP-006', 'type' => 'ONBOARDING', 'title' => 'Persiapan bergabung Maya', 'status' => 'PREPARING', 'date' => '2026-11-02', 'last_work' => null, 'period' => 'PER-006-2026', 'template' => 'ON-JKT-TECH-V1', 'access' => 'Belum diaktifkan', 'access_at' => null, 'note' => 'Akun belum siap: tugas IT masih menunggu verifikasi HR. Tugas wajib harus selesai sebelum aktivasi; pengecualian HR perlu dicatat. Prototipe tidak menyediakan aktivasi.', 'assets' => ['AST-003'], 'previous' => null,
                'tasks' => [
                    self::task('ON-01', 'Verifikasi dokumen contoh', 'Fajar Nugraha · HR', '2026-10-26', 'IN_PROGRESS', true, null, 'Belum ada bukti penyelesaian'),
                    self::task('ON-02', 'Siapkan akun kerja', 'Petugas IT contoh', '2026-10-28', 'BLOCKED', true, 'ON-01', 'Menunggu verifikasi HR; akun belum dibuat'),
                    self::task('ON-03', 'Serahkan perangkat', 'Petugas aset contoh', '2026-11-02', 'WAITING', true, 'ON-02', 'Laptop dipesan; belum ada serah terima'),
                    self::task('ON-04', 'Orientasi tim', 'Ratna Putri · Atasan', '2026-11-02', 'WAITING', false, 'ON-02', 'Agenda contoh, belum terlaksana'),
                ]],
            ['id' => 'LIF-002', 'employee' => 'EMP-009', 'type' => 'OFFBOARDING', 'title' => 'Persiapan akhir kerja Raka', 'status' => 'IN_PROGRESS', 'date' => '2026-10-31', 'last_work' => '2026-10-30', 'period' => 'PER-009-2022', 'template' => 'OFF-SBY-OPS-V1', 'access' => 'Masih aktif sampai jadwal akhir', 'access_at' => '2026-11-01 00:00 WIB', 'note' => 'Raka masih dapat bekerja selama periode yang berlaku. Pengembalian laptop tetap ditagih terpisah dan tidak boleh menunda pencabutan akses terjadwal.', 'assets' => ['AST-001'], 'previous' => null,
                'tasks' => [
                    self::task('OFF-01', 'Konfirmasi tanggal akhir kerja', 'Lestari Anindya · HR', '2026-10-19', 'DONE', true, null, 'EV-DEMO-901 · dikonfirmasi 19 Oktober'),
                    self::task('OFF-02', 'Serah terima pekerjaan', 'Bima Prasetya · Atasan', '2026-10-30', 'IN_PROGRESS', true, 'OFF-01', 'Daftar pekerjaan contoh belum ditutup'),
                    self::task('OFF-03', 'Pengembalian laptop', 'Petugas aset contoh', '2026-10-31', 'WAITING', true, 'OFF-01', 'Belum ada bukti pengembalian'),
                    self::task('OFF-04', 'Tinjau penyelesaian keuangan', 'Ayu Pertiwi · Finance', '2026-10-30', 'WAITING', true, 'OFF-01', 'Belum ada keputusan penyelesaian'),
                    self::task('OFF-05', 'Cabut akses aplikasi', 'Petugas IT contoh', '2026-11-01', 'SCHEDULED', true, null, 'Jadwal ilustrasi; bukan hasil scheduler'),
                ]],
            ['id' => 'LIF-003', 'employee' => 'EMP-011', 'type' => 'OFFBOARDING', 'title' => 'Clearance setelah akhir kerja Intan', 'status' => 'OPEN_CLEARANCE', 'date' => '2026-09-30', 'last_work' => '2026-09-30', 'period' => 'PER-011-2021', 'template' => 'OFF-BDG-OPS-V1', 'access' => 'Dicabut dalam skenario', 'access_at' => '2026-10-01 00:00 WIB', 'note' => 'Hubungan kerja sudah berakhir dan akses aplikasi dicabut dalam skenario meski laptop belum kembali. Tidak ada akses alumni produksi atau potongan gaji otomatis.', 'assets' => ['AST-002'], 'previous' => null,
                'tasks' => [
                    self::task('CLR-01', 'Tutup periode kerja', 'Lestari Anindya · HR', '2026-09-30', 'DONE', true, null, 'EV-DEMO-1101 · akhir periode tersimpan'),
                    self::task('CLR-02', 'Cabut akses aplikasi', 'Petugas IT contoh', '2026-10-01', 'DONE', true, null, 'EV-DEMO-1102 · pencabutan aplikasi contoh'),
                    self::task('CLR-03', 'Konfirmasi akun IT eksternal', 'Petugas IT contoh', '2026-10-01', 'DONE', true, null, 'EV-DEMO-1103 · konfirmasi manual petugas, bukan integrasi otomatis'),
                    self::task('CLR-04', 'Kembalikan laptop', 'Petugas aset contoh', '2026-09-30', 'OVERDUE', true, null, 'Belum ada bukti; tindak lanjut CASE-AST-002 terbuka'),
                ]],
            ['id' => 'LIF-004', 'employee' => 'EMP-010', 'type' => 'ONBOARDING', 'title' => 'Arsip bergabung kembali Fajar', 'status' => 'COMPLETED', 'date' => '2024-09-02', 'last_work' => null, 'period' => 'PER-010-2024', 'template' => 'ON-JKT-HR-V1', 'access' => 'Aktif pada periode sekarang', 'access_at' => null, 'note' => 'Bergabung kembali membuat periode dan checklist baru. Clearance periode sebelumnya tetap tersimpan, bukan dibuka ulang atau ditimpa.', 'assets' => ['AST-004', 'ITM-001'], 'previous' => ['period' => 'PER-010-2022', 'ended' => '2023-08-31', 'clearance' => 'CLR-DEMO-010-2023 · selesai', 'evidence' => 'EV-DEMO-1001 · perangkat periode lama telah kembali'],
                'tasks' => [
                    self::task('RE-01', 'Verifikasi periode baru', 'Lestari Anindya · HR', '2024-08-30', 'DONE', true, null, 'EV-DEMO-1002 · periode baru terverifikasi'),
                    self::task('RE-02', 'Aktivasi akses periode baru', 'Petugas IT contoh', '2024-09-02', 'DONE', true, 'RE-01', 'EV-DEMO-1003 · akun periode baru siap'),
                    self::task('RE-03', 'Serah terima perangkat baru', 'Petugas aset contoh', '2024-09-02', 'DONE', true, 'RE-02', 'EV-DEMO-1004 · serah terima tercatat'),
                ]],
        ];

        return array_map(fn ($row) => $row + ['person' => PrototypePeople::find($row['employee']), 'status_label' => self::states()[$row['status']], 'completed' => count(array_filter($row['tasks'], fn ($task) => $task['state'] === 'DONE'))], $rows);
    }

    private static function task(string $id, string $title, string $owner, string $due, string $state, bool $required, ?string $dependency, string $evidence): array
    {
        return compact('id', 'title', 'owner', 'due', 'state', 'required', 'dependency', 'evidence');
    }

    public static function taskStates(): array
    {
        return ['DONE' => 'Selesai', 'IN_PROGRESS' => 'Dikerjakan', 'BLOCKED' => 'Terhalang', 'WAITING' => 'Belum dimulai', 'SCHEDULED' => 'Terjadwal', 'OVERDUE' => 'Lewat tenggat'];
    }

    public static function find(string $id): ?array
    {
        return collect(self::cases())->firstWhere('id', $id);
    }

    public static function assetStates(): array
    {
        return ['IN_USE' => 'Dipakai', 'AWAITING_RETURN' => 'Menunggu kembali', 'RESERVED' => 'Dipesan', 'DAMAGED' => 'Rusak · ditinjau', 'ISSUED' => 'Habis pakai · diserahkan'];
    }

    public static function assets(): array
    {
        $rows = [
            ['id' => 'AST-001', 'name' => 'Laptop operasional', 'serial' => 'DEMO-SBY-001', 'category' => 'Laptop', 'branch' => 'Surabaya', 'condition' => 'Baik pada serah terima', 'status' => 'IN_USE', 'employee' => 'EMP-009', 'returnable' => true, 'case_id' => 'LIF-002', 'custody' => [['employee' => 'EMP-009', 'from' => '2022-01-10', 'until' => null, 'due' => '2026-10-31', 'evidence' => 'EV-DEMO-A001 · diterima; pengembalian belum ada']], 'settlement' => null],
            ['id' => 'AST-002', 'name' => 'Laptop arsip clearance', 'serial' => 'DEMO-BDG-002', 'category' => 'Laptop', 'branch' => 'Bandung', 'condition' => 'Belum diperiksa saat kembali', 'status' => 'AWAITING_RETURN', 'employee' => 'EMP-011', 'returnable' => true, 'case_id' => 'LIF-003', 'custody' => [['employee' => 'EMP-011', 'from' => '2021-10-04', 'until' => null, 'due' => '2026-09-30', 'evidence' => 'EV-DEMO-A002 · diterima; belum ada pengembalian']], 'settlement' => ['id' => 'CASE-AST-002', 'status' => 'Pengumpulan bukti', 'note' => 'Tindak lanjut pengembalian terbuka. Belum dinyatakan hilang dan belum ada nominal penyelesaian.']],
            ['id' => 'AST-003', 'name' => 'Laptop untuk karyawan baru', 'serial' => 'DEMO-JKT-003', 'category' => 'Laptop', 'branch' => 'Jakarta', 'condition' => 'Baik · di inventaris IT', 'status' => 'RESERVED', 'employee' => 'EMP-006', 'returnable' => true, 'case_id' => 'LIF-001', 'custody' => [], 'settlement' => null],
            ['id' => 'AST-004', 'name' => 'Laptop HR', 'serial' => 'DEMO-JKT-004', 'category' => 'Laptop', 'branch' => 'Jakarta', 'condition' => 'Baik', 'status' => 'IN_USE', 'employee' => 'EMP-010', 'returnable' => true, 'case_id' => 'LIF-004', 'custody' => [['employee' => 'EMP-002', 'from' => '2023-01-02', 'until' => '2024-08-29', 'due' => null, 'evidence' => 'EV-DEMO-A004-1 · pengembalian diperiksa'], ['employee' => 'EMP-010', 'from' => '2024-09-02', 'until' => null, 'due' => null, 'evidence' => 'EV-DEMO-A004-2 · serah terima periode baru']], 'settlement' => null],
            ['id' => 'AST-005', 'name' => 'Headset pemasaran', 'serial' => 'DEMO-BDG-005', 'category' => 'Periferal', 'branch' => 'Bandung', 'condition' => 'Mikrofon rusak · pemeriksaan contoh', 'status' => 'DAMAGED', 'employee' => 'EMP-008', 'returnable' => true, 'case_id' => null, 'custody' => [['employee' => 'EMP-008', 'from' => '2023-06-05', 'until' => null, 'due' => null, 'evidence' => 'EV-DEMO-A005 · laporan kerusakan 19 Oktober 2026']], 'settlement' => ['id' => 'CASE-AST-005', 'status' => 'Dalam tinjauan', 'note' => 'Menunggu bukti dan keputusan petugas. Belum ada tanggungan finansial yang diputuskan.']],
            ['id' => 'ITM-001', 'name' => 'Paket alat tulis', 'serial' => null, 'category' => 'Habis pakai', 'branch' => 'Jakarta', 'condition' => '1 paket diserahkan 2 September 2024', 'status' => 'ISSUED', 'employee' => 'EMP-010', 'returnable' => false, 'case_id' => 'LIF-004', 'custody' => [], 'settlement' => null],
        ];

        return array_map(fn ($row) => $row + ['person' => PrototypePeople::find($row['employee']), 'status_label' => self::assetStates()[$row['status']]], $rows);
    }

    public static function asset(string $id): ?array
    {
        return collect(self::assets())->firstWhere('id', $id);
    }
}
