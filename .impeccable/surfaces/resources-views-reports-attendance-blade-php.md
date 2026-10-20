---
version: 1
slug: "resources-views-reports-attendance-blade-php"
primary_target: "resources/views/reports/attendance.blade.php"
related_targets: ["resources/views/reports/periods.blade.php","resources/views/reports/period.blade.php"]
---

# Laporan & Penutupan Kehadiran

Scope: `/prototype/attendance-reports`, `/prototype/attendance-periods`, `/prototype/attendance-periods/{period}`. Mode Operate. Perluasan Kehadiran di Ruang Tim, fixture sintetis baca saja.

THESIS: Pengguna memahami definisi jumlah kehadiran, mengikuti shift sumber dan membedakan laporan berjalan dari snapshot yang sudah ditutup.

OWN-WORLD: memakai palet, tipografi, native GET filters, focus ring, tabel dan panel putih incumbent. Global tujuh tujuan tetap, lokal Kehadiran kini Rekap/Jadwal/Laporan/Penutupan periode. Tidak ada sidebar, raster atau animasi baru.

STORY: pilih minggu/cabang/departemen/karyawan, baca jumlah per hasil, ikuti baris sumber ke detail shift dan kembali dengan filter utuh. Lihat periode Terbuka/Dalam tinjauan/Ditutup; periksa penghambat, versi sumber dan riwayat. Snapshot tertutup menyimpan data periode, bukan hasil menghitung profil sekarang.

FIRST VIEWPORT: judul/snapshot, navigasi lokal, filter dan ringkasan tabel. Shift sumber di bawah. Detail periode mendahulukan versi/cakupan sebelum kesiapan/riwayat dan arsip. Signature interaction: Nadia19Okt tercatat satu Hadir selesai dengan terlambat dan pulang awal sebagai dua subset, bukan tiga kehadiran.

FORM: extension sistem UI. Native filters dan source links bekerja tanpa JavaScript; area tabel fokusable dan horizontal scroll. Tidak ada tombol penutupan/pembukaan ulang/ekspor palsu. Penghambat dan peringatan ditulis eksplisit.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Data dan batas

Laporan berasal dari `PrototypeAttendance`, dua minggu12–18/19–25Okt, lima karyawan. Hadir selesai adalah PRESENT dengan dua clock fixture; terlambat/pulang awal hanya subset PRESENT. IN_PROGRESS tetap sendiri meski clock-in terlambat; rejected attempt bukan hadir. Libur tetap baris roster, sehingga total baris bukan jumlah hari kerja/hadir. Filter departemen/cabang memakai profil saat ini untuk data berjalan.

Tiga periode contoh: OPEN cuplikan12–25Okt; REVIEW12–18Okt dengan V2 berbeda dari tinjauanV1 dan dua shift incomplete; CLOSED cuplikan28–30Sep tiga karyawan termasukIntan dengan snapshot terpisah, referensi arsip teks dan checksumSHA256 atas fixture. Arsip bukan bulan penuh atau raw event September yang tersedia untuk drilldown. Final payrollSeptember terkait; koreksi sesudah final perlu penyesuaian, tidak overwrite. Peninjauan ulang sebelum final memerlukan izin/alasan/revisi pada desain operasional, belum implementasi.

Perbandingan rencana3jam/aktual2jam adalah penjelasan aturan contoh, bukan payroll calculation. OVT-007 tetap rencana4jam tanpa aktual terverifikasi dan tidak masuk input terbayar. Tidak ada rate/prorata, locks, hash concurrency check, validator penutupan lengkap, ekspor spreadsheet, persistence atau mutasi. Snapshot dan checksum fixture tidak membuktikan backendclose. Belum memenuhi acceptance reports/close R1.

## Verifikasi

Gate local/testing dan GET-only, input whitelist, invalid query/unknown ID dan empty/reset diperiksa. Source drilldown mempertahankan minggu, cabang, departemen dan karyawan ke detail lalu kembali. Browser inventory kosong pada tahap ini; verifikasi render/aksesibilitas belum tersedia.
