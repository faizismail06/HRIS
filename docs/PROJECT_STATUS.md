# Status proyek

Status setelah penyelesaian cakupan visual statis. Dokumen ini membedakan implementasi layar, pengesahan visual browser dan implementasi domain; jumlah commit atau jumlah layar bukan ukuran kesiapan produksi.

## Tahap saat ini

Urutan pengerjaan proyek ini:

1. Fondasi proyek dan GitHub: tersedia. Laravel/Blade/Vite, dependency lockfiles, preview gate dan workflow branch `dev` sudah dipakai.
2. Visual statis: **cakupan layar inti telah dilengkapi; QA browser masih tertunda**. Daftar/detail modul, tujuh beranda peran, 48 formulir (awal/validasi gagal), 12 state dan panduan lintas modul tersedia. Ini bukan aplikasi HRIS operasional atau pengesahan kualitas render.
3. Implementasi backend/domain: **fondasi identitas dan skema CoreHR tersedia**. Login/MFA/reset, grants, pencabutan sesi, direktori kerja berizin, audit append-only serta migrasi organisasi/riwayat sudah diimplementasikan. CRUD dan workflow domain belum tersedia; ini belum acceptance R0.
4. Integrasi, pengujian end-to-end, UAT dan deployment: **belum selesai/belum dimulai untuk domain**. Build dan tes preview tidak menggantikan tahap ini.

Tahap pengerjaan di atas bukan penamaan release R0–R3 pada PRD. R0–R3 adalah kelompok kebutuhan produk yang belum boleh dianggap diterima hanya karena preview UI-nya tersedia.

## Peta cakupan

| Area PRD | Kondisi UI | Kondisi operasional |
| --- | --- | --- |
| Dashboard dan navigasi | Dashboard HR dan tujuh beranda peran dengan navigasi contoh tanpa sidebar | Persona bukan akun; belum dashboard berdasarkan izin/login |
| Karyawan dan organisasi | Direktori, profil, riwayat dan struktur contoh | Skema dan direktori backend berizin tersedia terpisah; belum create/edit/import nyata |
| Jadwal dan kehadiran | Roster, rekap dan detail event sintetis | Belum publikasi jadwal, clocking, GPS/foto atau koreksi tersimpan |
| Cuti dan pengajuan | Daftar, progres tahap, saldo dan kalender | Belum submit/approve, engine workflow, transaksi saldo atau notifikasi |
| Kontrak, surat dan karier | Versi, snapshot HTML, tanggal efektif dan perbandingan | Belum penomoran, penyimpanan privat, PDF atau perubahan efektif otomatis |
| Onboarding/offboarding dan aset | Checklist, pemilik tugas, custody dan kasus contoh | Belum aktivasi/pencabutan akun, serah terima atau settlement |
| Payroll dan reimbursement | Snapshot, penghambat, komponen, slip HTML dan pembayaran contoh | Belum kalkulasi regulasi, finalisasi, pembayaran, bank export atau akses slip privat |
| Laporan dan penutupan | Agregasi mingguan, sumber shift dan arsip contoh | Belum close/reopen, snapshot transaksi, lock, ekspor atau rekonsiliasi nyata |
| Pengaturan, audit dan impor | Matriks target, peristiwa sintetis dan validasi batch contoh | Audit identitas append-only tersedia di backend; pengaturan dan impor belum operasional |
| Notifikasi dan pencarian lintas modul | Kotak masuk/status pengiriman contoh dan pencarian ringkasan sepuluh jenis catatan | Belum inbox/outbox tersimpan, pengiriman/retry, preferensi aktif atau indeks berizin |
| Pengalaman Employee/Manager dan formulir mutasi | Beranda peran, profil/shift/saldo/tugas, 48 form awal/invalid dan 12 state statis | Seluruh field/aksi mutasi dinonaktifkan; belum alur submit sampai hasil akhir |
| Demo terisolasi | Form persona/lokasi/waktu skenario dan konfirmasi seed/reset statis | Belum instalasi demo, skenario terisolasi, persona auth atau reset berizin |

## Batas penyelesaian visual statis

Pintu masuk [Panduan layar statis](http://127.0.0.1:8000/prototype/screens) menautkan seluruh kelompok visual inti. [Inventaris layar](STATIC_SCREENS.md) mencatat 48 form dalam dua kondisi, tujuh beranda dan 12 state beserta pemetaan PRD. Field form, login, file dan aksi transaksi dinonaktifkan; yang berfungsi adalah navigasi/filter preview. Tiga rute peninjauan lintas modul bukan satu transaksi end-to-end tersambung.

Review browser desktop/ponsel, keyboard, zoom, kontras dan screen reader **belum disahkan**. Browser IAB dicoba langsung tetapi tidak tersedia; verdict visual tetap `recapture`. CSS responsif dan source semantics sudah diimplementasikan, tetapi tes HTTP/build bukan bukti hasil render. Aksi mutasi, otorisasi, realtime states dan semua interaksi operasional menunggu backend/integrasi. Penyelesaian statis bukan acceptance R0–R3.

## Backend pertama

Rute `/login`, `/account/security`, `/app` dan direktori `/app/employees` memakai akun serta database nyata, terpisah dari fixture preview. MFA wajib sebelum akses data. Belum ada akun nyata yang dibuat otomatis; operator melakukan bootstrap lewat terminal tepercaya. Panduan, matriks scope dan batas implementasi ada di [IDENTITY_FOUNDATION.md](IDENTITY_FOUNDATION.md). Entri tabel di atas tetap menjelaskan cakupan preview; fondasi baru tidak mengaktifkan aksi pada formulir statis.

## Sebelum dipakai operasional

Lanjutkan operasi create/update, transaksi workflow, aturan saldo/close/payroll dan pembatasan dokumen privat di atas fondasi identitas/CoreHR. Lalu uji alur end-to-end, konkurensi, kegagalan/retry, backup/restore dan keputusan kebijakan domain. Konfigurasi produksi, pengiriman email nyata dan QA browser belum disahkan. Belum ada klaim acceptance R0–R3 atau kesiapan produksi.

## Git dan verifikasi

Pengembangan berlangsung di `dev`. Modul yang selesai diperiksa dibuatkan commit fitur dan dokumentasi, lalu di-push sesuai instruksi pengguna. Tidak ada perubahan `main` atau force-push. Detail pemeriksaan terbaru dan batas fixture ada di [HANDOFF.md](HANDOFF.md). `.env` tidak dilacak dan tidak dibaca untuk membuat UI pengaturan.
