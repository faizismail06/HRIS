<?php

namespace App\Support;

/** Synthetic read-only fixtures, deliberately independent of operational models. */
class PrototypeDashboard
{
    public static function data(): array
    {
        return [
            'date' => 'Selasa, 20 Oktober 2026',
            'snapshot' => '08.45 WIB',
            'attendance' => [
                ['label' => 'Hadir valid', 'count' => 118, 'tone' => 'present'],
                ['label' => 'Belum mulai', 'count' => 9, 'tone' => 'scheduled'],
                ['label' => 'Cuti disetujui', 'count' => 5, 'tone' => 'leave'],
                ['label' => 'Belum lengkap', 'count' => 7, 'tone' => 'incomplete'],
                ['label' => 'Tidak hadir', 'count' => 3, 'tone' => 'absent'],
            ],
            'tasks' => [
                ['id' => 'COR-026', 'title' => 'Koreksi jam pulang', 'name' => 'Nadia Pramesti', 'initials' => 'NP', 'department' => 'Operasional', 'branch' => 'Jakarta', 'category' => 'Absensi', 'due' => 'Lewat 1 hari', 'priority' => 'urgent', 'owner' => 'HR Officer', 'description' => 'Contoh koreksi shift 18 Oktober, pukul 22.00–06.00 WIB. Jam pulang belum tercatat; manajer sudah meninjau bukti. HR perlu memeriksa kesesuaian sebelum keputusan akhir.', 'next' => 'Periksa bukti dan riwayat keputusan pada modul koreksi setelah modul tersedia.'],
                ['id' => 'DOC-018', 'title' => 'Verifikasi dokumen karyawan', 'name' => 'Arif Wicaksono', 'initials' => 'AW', 'department' => 'Keuangan', 'branch' => 'Bandung', 'category' => 'Administrasi', 'due' => 'Hari ini', 'priority' => 'urgent', 'owner' => 'HR Officer', 'description' => 'Contoh pemeriksaan kelengkapan dokumen untuk karyawan baru. Tidak ada dokumen identitas atau data keuangan nyata pada prototipe ini.', 'next' => 'Cocokkan kelengkapan dan catat hasil verifikasi melalui modul karyawan setelah tersedia.'],
                ['id' => 'ONB-009', 'title' => 'Kelengkapan onboarding', 'name' => 'Maya Larasati', 'initials' => 'ML', 'department' => 'Teknologi', 'branch' => 'Jakarta', 'category' => 'Administrasi', 'due' => 'Besok', 'priority' => 'normal', 'owner' => 'IT/Asset Officer', 'description' => 'Contoh onboarding dengan 4 dari 6 tugas selesai. Persiapan akun dan penerimaan perangkat masih menunggu pemilik tugas.', 'next' => 'Tindak lanjuti pemilik tugas IT; aktivasi harus mematuhi checklist wajib.'],
                ['id' => 'ATT-041', 'title' => 'Konflik cuti dan kehadiran', 'name' => 'Dimas Saputra', 'initials' => 'DS', 'department' => 'Operasional', 'branch' => 'Surabaya', 'category' => 'Absensi', 'due' => 'Besok', 'priority' => 'normal', 'owner' => 'HR Officer', 'description' => 'Contoh hari dengan cuti disetujui dan bukti kehadiran. Keduanya harus dipertahankan untuk peninjauan, tanpa menghapus bukti mentah.', 'next' => 'Konfirmasi dengan karyawan dan manajer sebelum rekonsiliasi.'],
                ['id' => 'PRO-012', 'title' => 'Tinjau perubahan profil', 'name' => 'Sinta Maharani', 'initials' => 'SM', 'department' => 'Pemasaran', 'branch' => 'Bandung', 'category' => 'Administrasi', 'due' => '22 Okt', 'priority' => 'normal', 'owner' => 'HR Officer', 'description' => 'Contoh pengajuan pembaruan kontak darurat. Nilai kontak tidak ditampilkan atau disimpan di prototipe.', 'next' => 'Periksa permintaan melalui modul profil setelah tersedia.'],
                ['id' => 'ATT-044', 'title' => 'Tinjau absensi belum lengkap', 'name' => 'Raka Adinata', 'initials' => 'RA', 'department' => 'Operasional', 'branch' => 'Surabaya', 'category' => 'Absensi', 'due' => '22 Okt', 'priority' => 'normal', 'owner' => 'Manajer Operasional', 'description' => 'Contoh shift dengan clock-in valid dan clock-out belum tercatat. Status belum lengkap tidak boleh dianggap otomatis sebagai tidak hadir.', 'next' => 'Minta klarifikasi atau pengajuan koreksi sesuai tenggat kebijakan.'],
            ],
            'agenda' => [
                ['time' => '09.00', 'title' => 'Orientasi karyawan baru', 'detail' => '2 karyawan · Jakarta', 'type' => 'Onboarding'],
                ['time' => '13.30', 'title' => 'Koordinasi jadwal cabang', 'detail' => 'Tim Operasional · Daring', 'type' => 'Koordinasi'],
                ['time' => '16.00', 'title' => 'Tinjauan kesiapan penutupan', 'detail' => 'Periode Oktober 2026', 'type' => 'Rekonsiliasi'],
            ],
        ];
    }
}
