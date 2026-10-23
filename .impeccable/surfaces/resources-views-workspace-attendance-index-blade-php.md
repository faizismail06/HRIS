---
version: 1
slug: "resources-views-workspace-attendance-index-blade-php"
primary_target: "resources/views/workspace/attendance/index.blade.php"
related_targets: ["resources/views/workspace/attendance/_row.blade.php", "resources/views/workspace/schedules/index.blade.php", "resources/views/workspace/schedules/show.blade.php", "resources/css/attendance-workspace.css"]
---

# Kehadiran dan roster operasional

Mode Operate. Memperluas Ruang Tim yang sudah dipilih: navigasi atas tanpa sidebar, plum, ground lavender tipis, panel putih, garis tipis dan font sistem. Tidak membuat identitas atau efek animasi baru.

Masalah: daftar operasional sebelumnya mengulang nama, tanggal dan paragraf dengan jarak besar; durasi nol muncul juga pada shift belum tercatat/libur. Backend hadir tetapi hierarchy presentasi belum memadai.

Struktur: tindakan Kalender tim/Kelola roster di heading; navigasi lokal rekap/lembur/penutupan; filter cakupan/tanggal horizontal dengan tautan hari ini/kemarin/tujuh hari; tabel semantik karyawan/tanggal kerja/jadwal/masuk/pulang/status/durasi/detail. Status ringkasan hanya menghitung halaman yang ditampilkan, tidak mengklaim total keseluruhan query berizin.

Waktu masuk/pulang memakai hasil efektif dari engine, termasuk koreksi yang disetujui. Shift malam memperlihatkan +1 hari dan tanggal waktu pulang. Durasi hanya tampil jika pasangan waktu tersedia; sedang bekerja diberi label Berlangsung; jadwal belum tercatat/libur memakai tanda pisah. Tidak mengubah perhitungan, tanggal default, cakupan akses, cursor pagination atau izin clocking.

Roster list dan detail memakai tabel yang sama; draf/publication, staffing warnings, checksum/version/idempotency, acknowledgement dan riwayat penggantian dipertahankan. Shift superseded diberi label telah diganti. Navigation Kehadiran tetap aktif saat membuka roster/lembur/penutupan.

Pada ponsel filter membungkus, tabel memiliki scroll region berlabel/focusable dan petunjuk geser. Verifikasi memakai Blade aktual + CSS build di Chrome terisolasi pada 1440/360 px. Capture berada di .cache/operation-browser dan bukan asset produk. Fixture browser tidak menggunakan data atau sesi pengguna. Seeder lokal tidak diubah atau diikutkan Git dalam pekerjaan ini.
