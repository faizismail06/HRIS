# Status proyek

Status setelah modul Pengaturan, Audit & Impor. Dokumen ini membedakan prototipe tampilan dari implementasi domain; jumlah commit atau jumlah layar bukan ukuran kesiapan produksi.

## Tahap saat ini

Urutan pengerjaan proyek ini:

1. Fondasi proyek dan GitHub: tersedia. Laravel/Blade/Vite, dependency lockfiles, preview gate dan workflow branch `dev` sudah dipakai.
2. Prototipe UI: **sedang dikerjakan**. Modul utama mempunyai layar baca saja dengan fixture sintetis, filter dan tautan detail. Ini bukan aplikasi HRIS operasional.
3. Implementasi backend/domain: **belum dikerjakan**. Login, policies, database bisnis, transaksi, workflow dan integrasi belum tersedia.
4. Integrasi, pengujian end-to-end, UAT dan deployment: **belum selesai/belum dimulai untuk domain**. Build dan tes preview tidak menggantikan tahap ini.

Tahap pengerjaan di atas bukan penamaan release R0–R3 pada PRD. R0–R3 adalah kelompok kebutuhan produk yang belum boleh dianggap diterima hanya karena preview UI-nya tersedia.

## Peta cakupan

| Area PRD | Kondisi UI | Kondisi operasional |
| --- | --- | --- |
| Dashboard dan navigasi | Preview HR tanpa sidebar | Persona bukan akun; belum dashboard berdasarkan izin/login |
| Karyawan dan organisasi | Direktori, profil, riwayat dan struktur contoh | Belum create/edit/import nyata, policies atau database bisnis |
| Jadwal dan kehadiran | Roster, rekap dan detail event sintetis | Belum publikasi jadwal, clocking, GPS/foto atau koreksi tersimpan |
| Cuti dan pengajuan | Daftar, progres tahap, saldo dan kalender | Belum submit/approve, engine workflow, transaksi saldo atau notifikasi |
| Kontrak, surat dan karier | Versi, snapshot HTML, tanggal efektif dan perbandingan | Belum penomoran, penyimpanan privat, PDF atau perubahan efektif otomatis |
| Onboarding/offboarding dan aset | Checklist, pemilik tugas, custody dan kasus contoh | Belum aktivasi/pencabutan akun, serah terima atau settlement |
| Payroll dan reimbursement | Snapshot, penghambat, komponen, slip HTML dan pembayaran contoh | Belum kalkulasi regulasi, finalisasi, pembayaran, bank export atau akses slip privat |
| Laporan dan penutupan | Agregasi mingguan, sumber shift dan arsip contoh | Belum close/reopen, snapshot transaksi, lock, ekspor atau rekonsiliasi nyata |
| Pengaturan, audit dan impor | Matriks target, peristiwa sintetis dan validasi batch contoh | Belum pengaturan tersimpan, audit append-only, upload/parser/commit atomik |
| Notifikasi dan pencarian lintas modul | Belum tersedia | Belum inbox/outbox, pengiriman atau indeks berizin |
| Pengalaman Employee/Manager dan formulir mutasi | Belum lengkap | Belum alur submit sampai hasil akhir |
| Demo terisolasi | Preview local/testing berlabel sintetis | Belum instalasi demo, skenario terisolasi, persona auth atau reset berizin |

## Pekerjaan UI berikutnya

Notifikasi/pencarian lintas modul, pengalaman Employee/Manager, formulir beserta state gagal/terlarang/stale/queued, dan penyatuan alur lintas modul masih perlu diselesaikan sesuai scope. Review browser desktop/ponsel, keyboard, zoom, kontras dan screen reader belum dapat disahkan karena browser sesi implementasi tidak tersedia. UI baca saja ini belum merupakan implementasi seluruh interaksi PRD.

## Sebelum dipakai operasional

Bangun autentikasi dan otorisasi server default deny; model/migrasi data bisnis serta audit; operasi create/update dan transaksi workflow; aturan efektif-dated, saldo/close/payroll dan pembatasan dokumen privat. Lalu uji alur end-to-end, izin antar pengguna, konkurensi, kegagalan/retry, backup/restore dan keputusan kebijakan domain. Belum ada klaim acceptance R0–R3 atau kesiapan produksi.

## Git dan verifikasi

Pengembangan berlangsung di `dev`. Modul yang selesai diperiksa dibuatkan commit fitur dan dokumentasi, lalu di-push sesuai instruksi pengguna. Tidak ada perubahan `main` atau force-push. Detail pemeriksaan terbaru dan batas fixture ada di [HANDOFF.md](HANDOFF.md). `.env` tidak dilacak dan tidak dibaca untuk membuat UI pengaturan.
