# Jadwal & Kehadiran

Scope: `/prototype/attendance`, `/prototype/schedules`, `/prototype/attendance/{record}`. Mode Operate. Perluasan Ruang Tim tanpa sidebar, dengan fixture UI baca saja; bukan engine attendance, geofencing, approval atau implementasi R1.

## Direction contract

THESIS: Pisahkan rencana shift, peristiwa clock dan hasil kehadiran agar tanggal kerja dan pengecualian mudah dipahami.

OWN-WORLD: mewarisi plum/white, native GET controls, panel 14 px, linework, tipografi dan focus Ruang Tim. Tidak ada palet, raster, dependency atau animasi masuk baru.

STORY: periksa rekap tanggal kerja, persempit karyawan/cabang/status, lihat jadwal mingguan, lalu buka detail beserta waktu mulai/selesai, raw event sintetis dan penjelasan status. Konteks scope terbawa pada pergantian tab dan tautan kembali.

FIRST VIEWPORT: header global empat menu, judul/snapshot dan dua tautan lokal Rekap/Jadwal. Rekap memakai form GET di atas ringkasan sebelum filter status dan tabel horizontal berlabel. Jadwal memakai rentang minggu dan tabel 7 kolom hari. Detail menempatkan penugasan dan jejak event berdampingan, penjelasan/panduan koreksi di bawah. Signature interaction: tautan satu shift malam membawa satu tanggal kerja sekaligus tanggal pulang berikutnya, tetap dengan scope filter asal.

FORM: extension sistem Ruang Tim yang telah dipilih. Global navigation tetap terbuka di atas, dengan gap mobile disesuaikan untuk empat tujuan.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Konten dan batas

70 baris contoh: 5 karyawan pada 12–25 Oktober 2026, 3 cabang WIB, snapshot tetap 20 Oktober 2026 pukul 08.45 WIB. Minggu yang tersedia hanya 12 dan 19 Oktober. Roster mengandung malam 22–06, pagi 08–16, siang 14–22 dan kantor 09–18 serta hari libur per orang. Jadwal adalah rencana; cuti tidak menghapus roster.

Contoh Nadia19: clock-in22.12, clock-out20Okt05.50, Hadir dengan terlambat12 dan pulangawal10; 398menit setelah istirahat60. Nadia18/Raka18 belum lengkap. Dimas19 libur; Fajar19 cuti; Dimas15 ditolak di luar radius lalu Tidak hadir pada snapshot; Dimas20 sedang bekerja. Raw events dan lokasi hanya cerita sintetis berlabel SIMULATED, bukan bukti terverifikasi. Tidak ada akses GPS/kamera, foto atau pengiriman koreksi. Panduan tujuh hari adalah default PRD dan belum menjadi kebijakan operasional.

Route GET-only dengan middleware flag/local-testing sebelumnya. Invalid date, week, employee, branch, state atau origin ditolak validasi. Unknown record404. Konteks kembali jadwal/rekap dipilih dari whitelist. Tabel tetap scroll horizontal focusable pada mobile, bukan menghapus kolom. Browser CUA saat ini inventory kosong; tidak ada klaim visual/a11y approval dari build atau tes.
