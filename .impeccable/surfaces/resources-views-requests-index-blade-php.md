---
version: 1
slug: "resources-views-requests-index-blade-php"
primary_target: "resources/views/requests/index.blade.php"
related_targets: ["resources/views/requests/show.blade.php","resources/views/requests/balances.blade.php","resources/views/requests/calendar.blade.php","resources/views/requests/_navigation.blade.php"]
---

# Cuti & Pengajuan

Scope: `/prototype/requests`, `/prototype/requests/{requestId}`, `/prototype/leave-balances`, `/prototype/team-calendar`. Mode Operate. Perluasan Ruang Tim dengan data sintetis baca saja.

## Direction contract

THESIS: Pemohon dan HR dapat memahami posisi pengajuan, pihak yang menunggu keputusan dan asal saldo tanpa menyamakan pengajuan tertunda dengan ketidakhadiran final.

OWN-WORLD: mewarisi warna plum, white surfaces, native GET forms, panel 14 px, data table dan focus styles Ruang Tim. Tidak ada identitas visual, raster, library atau animasi baru. Header global kini lima tujuan; pada layar sempit tautan tetap terlihat dan membungkus, tanpa sidebar.

STORY: filter delapan pengajuan, telusuri timeline tahap dan tenggat contoh, buka ledger saldo, lalu lihat ketersediaan mingguan tim. Detail mempertahankan konteks daftar atau saldo asal. Kalender hanya membawa ketersediaan, bukan payload alasan pribadi.

FIRST VIEWPORT: header global dan judul, tiga tautan lokal Pengajuan/Saldo/Kalender, lalu satu area kerja utama. Daftar memakai native GET filters dan tabel; detail memakai ringkasan + ordered approval steps; saldo menempatkan persamaan kredit/pemakaian/reservasi di atas ledger; kalender memakai tabel tujuh hari. Signature interaction: mengikuti referensi ledger ke detail dan kembali ke profil saldo yang sama, dengan reservasi dan pemakaian tetap berbeda.

FORM: extension dunia dan navigasi Ruang Tim yang dipilih pengguna. Menu Pengajuan ditambahkan karena modulnya kini tersedia. Tidak ada workflow operasional, aksi persetujuan atau pengiriman form mutasi.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Data dan aturan ilustrasi

Snapshot 20 Oktober 2026 pukul 08.45 WIB. Delapan pengajuan mencakup cuti, koreksi absensi, lembur, dan tukar shift. Status: Diajukan, Dalam tinjauan, Disetujui, Ditolak, Ditarik. Tahap aktif terpisah dari tahap menunggu; request ditolak/ditarik tidak memiliki approver aktif. Usia adalah sejak pengajuan, bukan lama menunggu tiap tahap. Tidak ada self-approval yang ditawarkan.

Saldo memakai integer setengah-hari: Nadia kredit24 unit, debit6 dan reservasi4, tersisa14 unit =7 hari; Arif tersedia9,5 hari dengan reservasi0,5; Dimas memiliki reserve/release seimbang pada pengajuan ditolak. Kredit12 hari adalah angka sintetis, bukan hak/kebijakan hukum. Tidak ada transaksi concurrency, accrual, carry-over, expiry atau rollback operasional.

Izin yang disetujui CUT-014 (Fajar19Okt) dan CUT-040 (Nadia23Okt) menjadi satu sumber fixture approvedLeaveDates untuk rekap dan kalender. CUT-041/CUT-042 yang belum final tidak mengubah kalender. Kalender hanya berisi Terjadwal/Tidak tersedia/Libur dan jumlah shift, bukan alasan pribadi, diagnosis, status pengajuan atau bukti clock aktual. Jumlah shift bukan kebutuhan staf per jam.

COR-026 belum mengubah raw event shift Nadia18. OVT-007 rencana melewati tengah malam dan belum berarti lembur dibayar. SWP-003 ditarik sebelum persetujuan rekan; roster asal tetap sama. Semua label, aktor, keputusan dan ledger adalah fixture, bukan bukti backend telah berfungsi.

## Verifikasi

Gate preview dan route GET-only dipertahankan; input query di-whitelist, unknown ID404, hasil kosong dengan reset, saldo detail memiliki back origin whitelist. Browser sebelumnya tidak tersedia; build/tes/review kode tidak membuktikan visual atau aksesibilitas. Tidak ada foto, file pribadi, GPS, notifikasi atau payment API.
