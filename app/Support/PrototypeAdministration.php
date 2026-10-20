<?php

namespace App\Support;

/** Synthetic configuration/audit/import UI. Never reads environment secrets or applies changes. */
class PrototypeAdministration
{
    public static function roles(): array
    {
        return [
            ['Employee', 'Catatan milik sendiri', 'Tidak memilih ID orang lain atau menyetujui pengajuan sendiri.'],
            ['Manager', 'Laporan langsung yang ditugaskan', 'Tidak otomatis mendapat gaji, rekening, pajak atau diagnosis anggota tim.'],
            ['HR Officer', 'Administrasi pada cabang/divisi berizin', 'Tidak otomatis dapat merilis payroll atau mengatur akses.'],
            ['HR Admin', 'Kebijakan HR dan workflow dalam scope', 'Tidak otomatis memiliki akses platform atau payroll tanpa batas.'],
            ['Payroll Officer', 'Masukan dan rekonsiliasi payroll', 'Tidak menyetujui batch yang disiapkan sendiri.'],
            ['Finance Approver', 'Hasil payroll/klaim dan pembayaran', 'Tidak mengubah sumber kehadiran atau membuka lampiran medis.'],
            ['IT/Asset Officer', 'Tugas akun/aset dan identitas minimum', 'Tidak membuka gaji, pajak atau alasan cuti pribadi.'],
            ['Auditor', 'Baca sesuai mandat eksplisit', 'Tidak mengubah transaksi; ekspor perlu izin terpisah.'],
            ['Platform Admin', 'Akun dan operasi teknis', 'Tidak otomatis mendapat data HR/payroll; akses darurat perlu alasan dan audit.'],
        ];
    }

    public static function modules(): array
    {
        return ['ACCESS' => 'Akses', 'IMPORT' => 'Impor', 'ATTENDANCE' => 'Kehadiran', 'EXPORT' => 'Ekspor'];
    }

    public static function results(): array
    {
        return ['SUCCESS' => 'Berhasil dalam skenario', 'DENIED' => 'Ditolak dalam skenario', 'FAILED' => 'Gagal dalam skenario'];
    }

    public static function audit(): array
    {
        return [
            ['id' => 'AUD-006', 'at' => '2026-10-20 08:30', 'module' => 'ACCESS', 'actor' => 'Admin platform contoh', 'action' => 'Perubahan scope akun contoh', 'result' => 'SUCCESS', 'subject' => 'ACC-DEMO-HR-02', 'before' => 'Cabang: Jakarta', 'after' => 'Cabang: Bandung', 'reason' => 'Contoh permintaan penugasan disetujui; bukan perubahan akun nyata.', 'correlation' => 'CORR-DEMO-006', 'target' => null],
            ['id' => 'AUD-005', 'at' => '2026-10-20 08:20', 'module' => 'ACCESS', 'actor' => 'Nadia Pramesti · pemohon contoh', 'action' => 'Percobaan persetujuan sendiri', 'result' => 'DENIED', 'subject' => 'CUT-041', 'before' => 'Dalam tinjauan', 'after' => 'Tetap dalam tinjauan', 'reason' => 'Pemohon tidak dapat menyetujui pengajuan sendiri.', 'correlation' => 'CORR-DEMO-005', 'target' => ['route' => 'prototype.requests.show', 'parameters' => ['requestId' => 'CUT-041']]],
            ['id' => 'AUD-004', 'at' => '2026-10-20 08:10', 'module' => 'EXPORT', 'actor' => 'Manager contoh', 'action' => 'Permintaan ekspor di luar mandat', 'result' => 'DENIED', 'subject' => 'EXPORT-DEMO-004', 'before' => 'Tidak ada ekspor', 'after' => 'Tidak ada ekspor', 'reason' => 'Scope tim tidak memberi akses data rekening. Payload sensitif tidak dimuat di audit contoh.', 'correlation' => 'CORR-DEMO-004', 'target' => null],
            ['id' => 'AUD-003', 'at' => '2026-10-20 08:00', 'module' => 'IMPORT', 'actor' => 'Petugas HR contoh', 'action' => 'Validasi batch impor contoh', 'result' => 'FAILED', 'subject' => 'IMP-001', 'before' => 'Batch dipetakan', 'after' => 'Validasi bermasalah · 0 perubahan', 'reason' => 'Duplikat business key dan nilai baris tidak valid. Tidak ada profil parsial yang dibuat.', 'correlation' => 'CORR-DEMO-003', 'target' => ['route' => 'prototype.imports.show', 'parameters' => ['batch' => 'IMP-001']]],
            ['id' => 'AUD-002', 'at' => '2026-10-20 07:50', 'module' => 'IMPORT', 'actor' => 'Petugas HR contoh', 'action' => 'Pengenalan berkas berulang', 'result' => 'DENIED', 'subject' => 'IMP-004', 'before' => '0 perubahan', 'after' => 'Tetap 0 perubahan', 'reason' => 'Hash dan jenis batch sama dengan IMP-003; ilustrasi pencegahan duplikasi.', 'correlation' => 'CORR-DEMO-002', 'target' => ['route' => 'prototype.imports.show', 'parameters' => ['batch' => 'IMP-004']]],
            ['id' => 'AUD-001', 'at' => '2026-09-30 18:30', 'module' => 'ATTENDANCE', 'actor' => 'Lestari Anindya · HR contoh', 'action' => 'Snapshot penutupan contoh', 'result' => 'SUCCESS', 'subject' => 'CLOSE-SEP-26', 'before' => 'Dalam tinjauan · revisi 1', 'after' => 'Snapshot arsip revisi 1', 'reason' => 'Cuplikan periode, bukan audit transaksi yang dihasilkan backend.', 'correlation' => 'CORR-DEMO-001', 'target' => ['route' => 'prototype.attendance-periods.show', 'parameters' => ['period' => 'CLOSE-SEP-26']]],
        ];
    }

    public static function importStates(): array
    {
        return ['INVALID' => 'Validasi bermasalah', 'READY' => 'Pratinjau siap ditinjau', 'COMMITTED' => 'Contoh selesai', 'DUPLICATE' => 'Berkas berulang'];
    }

    public static function importTypes(): array
    {
        return ['EMPLOYEE' => 'Karyawan', 'ROSTER' => 'Roster', 'BALANCE' => 'Saldo awal cuti'];
    }

    public static function imports(): array
    {
        $balanceRows = [
            ['row' => 2, 'values' => ['employee_code' => 'EMP-010', 'half_day_units' => '24'], 'errors' => []],
            ['row' => 3, 'values' => ['employee_code' => 'EMP-004', 'half_day_units' => '24'], 'errors' => []],
        ];
        $rows = [
            ['id' => 'IMP-004', 'file' => 'saldo-awal-contoh-ulang.csv', 'type' => 'BALANCE', 'status' => 'DUPLICATE', 'date' => '2026-10-20', 'applied' => 0, 'previous' => 'IMP-003', 'mapping' => ['kode' => 'employee_code', 'unit_setengah_hari' => 'half_day_units'], 'rows' => $balanceRows, 'note' => 'Payload dan jenis sama dengan IMP-003. Skenario retry tidak membuat saldo dua kali. Tidak ada commit atau deduplikasi operasional yang dijalankan.'],
            ['id' => 'IMP-001', 'file' => 'karyawan-contoh.csv', 'type' => 'EMPLOYEE', 'status' => 'INVALID', 'date' => '2026-10-20', 'applied' => 0, 'previous' => null, 'mapping' => ['nomor' => 'employee_code', 'nama' => 'name', 'cabang' => 'branch'], 'rows' => [
                ['row' => 2, 'values' => ['employee_code' => 'EMP-DEMO-201', 'name' => 'Nama Contoh Satu', 'branch' => 'Jakarta'], 'errors' => []],
                ['row' => 3, 'values' => ['employee_code' => 'EMP-DEMO-201', 'name' => 'Nama Contoh Dua', 'branch' => 'Bandung'], 'errors' => [['column' => 'employee_code', 'reason' => 'Duplikat nomor pada baris 2', 'remedy' => 'Gunakan business key unik; jangan menimpa baris sebelumnya.']]],
                ['row' => 4, 'values' => ['employee_code' => 'EMP-DEMO-203', 'name' => '=2+3', 'branch' => 'Cabang tidak dikenal'], 'errors' => [['column' => 'name', 'reason' => 'Teks diawali formula', 'remedy' => 'Isi nama sebagai teks; formula tidak dieksekusi.'], ['column' => 'branch', 'reason' => 'Cabang tidak ada dalam master contoh', 'remedy' => 'Gunakan Jakarta, Bandung atau Surabaya.']]],
            ], 'note' => 'Ada kesalahan bisnis: seluruh batch ditolak dalam skenario, termasuk baris yang lolos. Tidak ada profil parsial atau overwrite diam-diam.'],
            ['id' => 'IMP-002', 'file' => 'roster-contoh.csv', 'type' => 'ROSTER', 'status' => 'READY', 'date' => '2026-10-19', 'applied' => 0, 'previous' => null, 'mapping' => ['kode' => 'employee_code', 'mulai' => 'starts_at', 'selesai' => 'ends_at'], 'rows' => [
                ['row' => 2, 'values' => ['employee_code' => 'EMP-004', 'starts_at' => '2026-11-02 22:00 WIB', 'ends_at' => '2026-11-03 06:00 WIB'], 'errors' => []],
                ['row' => 3, 'values' => ['employee_code' => 'EMP-010', 'starts_at' => '2026-11-02 09:00 WIB', 'ends_at' => '2026-11-02 18:00 WIB'], 'errors' => []],
            ], 'note' => 'Baris contoh siap ditinjau, belum diterapkan atau dipublikasikan sebagai roster. Tombol commit tidak tersedia pada prototipe baca saja.'],
            ['id' => 'IMP-003', 'file' => 'saldo-awal-contoh.csv', 'type' => 'BALANCE', 'status' => 'COMMITTED', 'date' => '2026-10-19', 'applied' => 2, 'previous' => null, 'mapping' => ['kode' => 'employee_code', 'unit_setengah_hari' => 'half_day_units'], 'rows' => $balanceRows, 'note' => 'Contoh tampilan batch selesai dengan dua baris dalam skenario. Tidak ada saldo yang ditulis atau diubah oleh UI ini. Nilai 24 unit = 12 hari hanya angka sintetis.'],
        ];

        return array_map(fn ($row) => $row + ['hash' => hash('sha256', json_encode(['type' => $row['type'], 'rows' => $row['rows']])), 'invalid_rows' => count(array_filter($row['rows'], fn ($record) => count($record['errors']) > 0))], $rows);
    }

    public static function import(string $id): ?array
    {
        return collect(self::imports())->firstWhere('id', $id);
    }
}
