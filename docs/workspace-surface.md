# Notifikasi & Pencarian Lintas Modul

Scope: `/prototype/notifications`, `/prototype/notifications/{notification}`, `/prototype/search`. Mode Operate. Extension Ruang Tim, fixture sintetis baca saja.

THESIS: Pengguna menemukan catatan sumber dan memahami pemberitahuan tanpa menyamakan status pengiriman dengan keputusan bisnis.

OWN-WORLD: palet plum, Segoe UI, native GET forms, tabel/focus ring/panel incumbent. Global tetap tujuh tujuan. Dua tautan utilitas Pencarian dan Notifikasi tersedia di atas pemberitahuan prototipe, membungkus tanpa sidebar. Badge menunjukkan tiga belum dibaca dalam fixture, bukan badge realtime. Hasil pencarian berupa daftar teks berpemisah, tanpa kartu berulang, raster atau motion baru.

STORY: filter kotak masuk menurut kata kunci/status baca/status email, buka detail dan catatan sumber; kembali mempertahankan filter. Pencarian lintas sepuluh jenis menggunakan query minimal dua karakter dan filter kategori. Hasil menuju route sumber tetap; browser Back memulihkan URL pencarian. Query tidak diteruskan sebagai filter lokal yang berbeda makna.

FIRST VIEWPORT: utilitas global, judul/snapshot, filter dan daftar. Detail pesan mendahulukan isi serta tautan sumber lalu status pengiriman/kunci dedup/jadwal. Signature interaction: email COR-026 gagal tetapi pengajuan tetap dalam tinjauan. Search awal memberi petunjuk; hasil kosong menyediakan perluasan semua jenis.

FORM: extension dunia UI yang disetujui. Native controls dan field preferensi baca saja; tidak ada tombol tandai dibaca, arsip, retry, kirim atau simpan yang berpura-pura operasional. Status teks membedakan baca, pengiriman dan sumber.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Fixture dan batas

Enam pesan, tiga belum dibaca, dua dibaca dan satu arsip; membuka GET tidak mengubah state. DeliveryNONE/SENT/FAILED/QUEUED terpisah. COR-026 tiga percobaan dalam skenario sebelum jamtenang, menunggu09.00; CTR-001 antre jamtenang belum ada percobaan. Digest09.00 dan jamtenang21.00–09.00 adalah contoh, bukan setting akun aktif. Pesan tidak membawa nominal gaji, diagnosis atau lampiran. Kunci event berbeda perkejadian/tahap. Tidak ada outbox, pengiriman email, retryjob, eskalasi atau pembatalan transaksi sumber.

Pencarian memakai proyeksi terpilih dari fixture yang ada: karyawan, pengajuan, employment, lifecycle, aset, shift, batchpayroll, klaim, impor dan periodeclose. Ringkasan tidak mencakup nominal, komponen slip, email karyawan, alasan pribadi, isi surat, bukti klaim atau payload audit. Semua route/parameter sumber ditentukan kode, bukan input pengguna. Pencarian tidak menyediakan otorisasi/index produksi, masking perrole, pencatatan query atau backendsearch. Data hanya sintetis dan gate local/testing tetap berlaku.

## Verifikasi

GET-only, querywhitelist, unknownnotification404, empty/reset, statusfixture tidak berubah, kategori dan target lintasmodul diperiksa. Browser sebelumnya tidak tersedia; review kode/build/tes tidak membuktikan visual atau aksesibilitas. Tidak ada perubahan .env, file pribadi atau integrasi eksternal.
