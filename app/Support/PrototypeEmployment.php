<?php

namespace App\Support;

/** Document and career snapshots for UI only; no legal or operational documents. */
class PrototypeEmployment
{
    public static function definitions(): array
    {
        return [
            'contracts' => ['title' => 'Kontrak', 'description' => 'Masa berlaku, versi dan keputusan kontrak contoh.', 'states' => ['ACTIVE' => 'Aktif', 'SCHEDULED' => 'Terjadwal', 'IN_REVIEW' => 'Dalam tinjauan', 'EXPIRED' => 'Berakhir', 'SUPERSEDED' => 'Digantikan']],
            'letters' => ['title' => 'Surat', 'description' => 'Pratinjau snapshot surat dan hubungan penerbitan ulang.', 'states' => ['ISSUED' => 'Diterbitkan', 'DRAFT' => 'Draf', 'SUPERSEDED' => 'Digantikan']],
            'career-changes' => ['title' => 'Perubahan karier', 'description' => 'Nilai sebelum/sesudah dengan tanggal efektif yang jelas.', 'states' => ['SCHEDULED' => 'Terjadwal', 'IN_REVIEW' => 'Dalam tinjauan', 'EFFECTIVE' => 'Sudah efektif']],
        ];
    }

    public static function records(string $kind): array
    {
        $rows = match ($kind) {
            'contracts' => [
                ['id' => 'CTR-001', 'employee' => 'EMP-004', 'title' => 'Kontrak utama 2026', 'status' => 'ACTIVE', 'start' => '2026-01-01', 'end' => '2026-10-31', 'number' => 'CTR/DEMO/2026/004', 'version' => 'CON-DEMO-V1', 'job' => 'Koordinator Operasional', 'note' => 'Kontrak yang berlaku pada snapshot 20 Oktober. Versi November belum menggantikannya.', 'related' => ['kind' => 'contracts', 'id' => 'CTR-002', 'label' => 'Versi berikutnya · belum efektif']],
                ['id' => 'CTR-002', 'employee' => 'EMP-004', 'title' => 'Perpanjangan mulai November', 'status' => 'SCHEDULED', 'start' => '2026-11-01', 'end' => '2027-10-31', 'number' => 'CTR/DEMO/2026/104', 'version' => 'CON-DEMO-V2', 'job' => 'Supervisor Operasional', 'note' => 'Keputusan contoh sudah dijadwalkan; berlaku mulai 1 November, tidak mengubah kontrak atau jabatan hari ini.', 'related' => ['kind' => 'career-changes', 'id' => 'CAR-001', 'label' => 'Perubahan penugasan terkait']],
                ['id' => 'CTR-003', 'employee' => 'EMP-004', 'title' => 'Arsip kontrak 2025', 'status' => 'SUPERSEDED', 'start' => '2025-01-01', 'end' => '2025-12-31', 'number' => 'CTR/DEMO/2025/004', 'version' => 'CON-DEMO-V1', 'job' => 'Koordinator Operasional', 'note' => 'Arsip tidak menimpa kontrak 2026 meskipun dibuka atau diimpor kemudian.', 'related' => ['kind' => 'contracts', 'id' => 'CTR-001', 'label' => 'Versi penerus']],
                ['id' => 'CTR-004', 'employee' => 'EMP-009', 'title' => 'Kontrak periode berjalan', 'status' => 'ACTIVE', 'start' => '2026-01-01', 'end' => '2026-10-31', 'number' => 'CTR/DEMO/2026/009', 'version' => 'CON-DEMO-V1', 'job' => 'Staf Operasional', 'note' => 'Profil sedang offboarding. Kontrak, akhir hubungan kerja dan pencabutan akses adalah proses berbeda.'],
                ['id' => 'CTR-005', 'employee' => 'EMP-011', 'title' => 'Arsip periode kerja selesai', 'status' => 'EXPIRED', 'start' => '2021-10-04', 'end' => '2026-09-30', 'number' => 'CTR/DEMO/2021/011', 'version' => 'CON-DEMO-V1', 'job' => 'Staf Operasional', 'note' => 'Arsip kontrak mantan karyawan tetap tersedia untuk riwayat.', 'related' => ['kind' => 'letters', 'id' => 'LTR-002', 'label' => 'Surat pengalaman kerja contoh']],
                ['id' => 'CTR-006', 'employee' => 'EMP-006', 'title' => 'Rancangan kontrak karyawan baru', 'status' => 'IN_REVIEW', 'start' => '2026-11-02', 'end' => '2027-11-01', 'number' => null, 'version' => 'CON-DEMO-V1', 'job' => 'Software Engineer', 'note' => 'Tanggal rancangan belum menjadi kontrak aktif. Peninjauan dan keputusan operasional belum tersedia.'],
            ],
            'letters' => [
                ['id' => 'LTR-001', 'employee' => 'EMP-004', 'title' => 'Surat keterangan kerja', 'status' => 'SUPERSEDED', 'start' => '2026-08-12', 'number' => 'SKK/DEMO/2026/001', 'version' => 'SKK-DEMO-V1', 'note' => 'Versi asli tetap tersimpan setelah koreksi ejaan unit melalui penerbitan ulang.', 'snapshot' => ['name' => 'Nadia Pramesti', 'employee' => 'EMP-004', 'job' => 'Koordinator Operasional', 'department' => 'Operasi', 'branch' => 'Jakarta', 'period' => 'Bergabung 7 Agustus 2023'], 'related' => ['kind' => 'letters', 'id' => 'LTR-004', 'label' => 'Penerbitan ulang terkait']],
                ['id' => 'LTR-004', 'employee' => 'EMP-004', 'title' => 'Surat keterangan kerja · terbit ulang', 'status' => 'ISSUED', 'start' => '2026-08-13', 'number' => 'SKK/DEMO/2026/002', 'version' => 'SKK-DEMO-V1', 'note' => 'Koreksi ejaan unit, bukan penggantian diam-diam dokumen asli. Snapshot jabatan tetap Koordinator walau ada promosi terjadwal.', 'snapshot' => ['name' => 'Nadia Pramesti', 'employee' => 'EMP-004', 'job' => 'Koordinator Operasional', 'department' => 'Operasional', 'branch' => 'Jakarta', 'period' => 'Bergabung 7 Agustus 2023'], 'related' => ['kind' => 'letters', 'id' => 'LTR-001', 'label' => 'Dokumen asli yang dipertahankan']],
                ['id' => 'LTR-002', 'employee' => 'EMP-011', 'title' => 'Surat pengalaman kerja', 'status' => 'ISSUED', 'start' => '2026-10-01', 'number' => 'SPK/DEMO/2026/003', 'version' => 'SPK-DEMO-V1', 'note' => 'Menggunakan periode kerja yang sudah selesai, bukan profil aktif atau masa kerja perkiraan.', 'snapshot' => ['name' => 'Intan Kusuma', 'employee' => 'EMP-011', 'job' => 'Staf Operasional', 'department' => 'Operasional', 'branch' => 'Bandung', 'period' => '4 Oktober 2021 – 30 September 2026']],
                ['id' => 'LTR-003', 'employee' => 'EMP-006', 'title' => 'Rancangan surat penugasan', 'status' => 'DRAFT', 'start' => '2026-10-18', 'number' => null, 'version' => 'ASG-DEMO-V1', 'note' => 'Belum diterbitkan, belum memiliki nomor final dan tidak mengaktifkan karyawan.', 'snapshot' => ['name' => 'Maya Larasati', 'employee' => 'EMP-006', 'job' => 'Software Engineer', 'department' => 'Teknologi', 'branch' => 'Jakarta', 'period' => 'Rencana mulai 2 November 2026']],
            ],
            'career-changes' => [
                ['id' => 'CAR-001', 'employee' => 'EMP-004', 'title' => 'Promosi terjadwal', 'status' => 'SCHEDULED', 'start' => '2026-11-01', 'number' => 'CAR/DEMO/2026/001', 'version' => 'CAR-DEMO-V1', 'note' => 'Disetujui dalam skenario 18 Oktober. Jabatan per 20 Oktober tetap Koordinator Operasional; perubahan baru efektif 1 November.', 'changes' => [['field' => 'Jabatan', 'before' => 'Koordinator Operasional', 'after' => 'Supervisor Operasional'], ['field' => 'Cabang', 'before' => 'Jakarta', 'after' => 'Jakarta'], ['field' => 'Atasan', 'before' => 'Bima Prasetya', 'after' => 'Bima Prasetya']], 'related' => ['kind' => 'contracts', 'id' => 'CTR-002', 'label' => 'Perpanjangan kontrak terjadwal']],
                ['id' => 'CAR-002', 'employee' => 'EMP-008', 'title' => 'Usulan perpindahan cabang', 'status' => 'IN_REVIEW', 'start' => '2026-11-15', 'number' => null, 'version' => 'CAR-DEMO-V1', 'note' => 'Masih dalam tinjauan. Usulan tidak mengubah cabang saat ini dan belum menjadi keputusan.', 'changes' => [['field' => 'Cabang', 'before' => 'Bandung', 'after' => 'Surabaya'], ['field' => 'Jabatan', 'before' => 'Spesialis Pemasaran', 'after' => 'Spesialis Pemasaran']]],
                ['id' => 'CAR-003', 'employee' => 'EMP-005', 'title' => 'Perubahan atasan langsung', 'status' => 'EFFECTIVE', 'start' => '2026-09-01', 'number' => 'CAR/DEMO/2026/003', 'version' => 'CAR-DEMO-V1', 'note' => 'Contoh keputusan yang sudah efektif. Profil saat ini merujuk Ratna Putri; riwayat keputusan terdahulu tetap ditampilkan.', 'changes' => [['field' => 'Atasan', 'before' => 'Bima Prasetya', 'after' => 'Ratna Putri'], ['field' => 'Cabang', 'before' => 'Bandung', 'after' => 'Bandung']]],
            ],
            default => [],
        };

        return array_map(fn ($row) => $row + ['kind' => $kind, 'person' => PrototypePeople::find($row['employee']), 'status_label' => self::definitions()[$kind]['states'][$row['status']]], $rows);
    }

    public static function find(string $kind, string $id): ?array
    {
        return collect(self::records($kind))->firstWhere('id', $id);
    }
}
