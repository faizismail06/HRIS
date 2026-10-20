---
version: 1
slug: "resources-views-employment-index-blade-php"
primary_target: "resources/views/employment/index.blade.php"
related_targets: ["resources/views/employment/show.blade.php","resources/views/employment/_navigation.blade.php","resources/views/employment/_state.blade.php"]
---

# Kontrak, Surat & Perubahan Karier

Scope: `/prototype/contracts`, `/prototype/letters`, `/prototype/career-changes` dan masing-masing detail `/{record}`. Mode Operate. Perluasan Ruang Tim, data sintetis baca saja.

## Direction contract

THESIS: HR membedakan catatan yang berlaku hari ini, arsip, usulan dan perubahan mendatang tanpa kehilangan versi sumber.

OWN-WORLD: palet plum, Segoe UI, panel putih 14 px, native GET filters, tabel dan focus ring dari Ruang Tim. Enam tujuan global tetap terlihat tanpa sidebar. Pada lebar 1100 px ke bawah, navigasi berpindah ke baris sendiri dan dapat membungkus. Tidak ada aset raster atau motion baru.

STORY: masuk melalui Administrasi, pilih Kontrak/Surat/Perubahan karier, filter lalu buka catatan; telusuri versi terkait atau profil saat ini. Detail dan kembali ke daftar mempertahankan filter. Perpindahan jenis hanya membawa cabang dan karyawan karena status dan pencarian berbeda konteks.

FIRST VIEWPORT: judul dan snapshot tanggal, tiga tautan lokal lalu register berfilter. Detail menempatkan status, nomor, versi template dan periode sebelum catatan lanjutan. Signature interaction: menelusuri kontrak terjadwal ke perubahan sebelum/sesudah tanpa menggantikan profil hari ini. Surat menyajikan isi snapshot dengan cap contoh, tanpa tanda tangan palsu.

FORM: perluasan sistem yang disetujui, bukan pergantian dunia visual. Administrasi mengelompokkan tiga tujuan terkait sehingga header global tetap ringkas. Tabel tetap semantik dengan area scroll yang dapat difokuskan; kontrol pencarian/filter memakai GET dan tidak bergantung JavaScript.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Fixture dan batas implementasi

Enam kontrak meliputi aktif, terjadwal, dalam tinjauan, digantikan dan berakhir. Nadia tetap Koordinator pada 20 Oktober; kontrak Supervisor dimulai 1 November dan tidak menimpa versi berjalan. Raka sedang offboarding dengan kontrak aktif sampai 31 Oktober. Arsip Intan tetap tersedia setelah akhir kerja. Tanggal reminder 60/30/14/7 hari hanya ilustrasi untuk kontrak aktif; tidak ada scheduler atau pengiriman notifikasi.

Empat surat memakai snapshot nama, jabatan, unit, cabang dan periode tersendiri. LTR-001 mempertahankan ejaan unit lama “Operasi”, LTR-004 menjadi penerbitan ulang “Operasional” dengan nomor baru dan tautan dua arah. LTR-002 memakai periode kerja Intan yang sudah selesai. Draf Maya belum memiliki nomor final. Semua versi template bersifat sintetis. Isi hanya HTML, tidak ada PDF, unggahan, unduhan, penyimpanan dokumen privat atau pengesahan.

Tiga perubahan karier menampilkan nilai sebelum/sesudah: promosi Nadia terjadwal, usulan cabang Sinta, dan perubahan atasan Arif yang sudah efektif. Filter cabang memakai profil saat ini, bukan cabang dalam snapshot. Tidak ada mesin tanggal efektif, persetujuan, transaksi versi, nomor unik operasional, periode tertutup atau penyesuaian retroaktif. Ini belum memenuhi acceptance modul employment R1.

## Verifikasi

Gate preview local/testing, default flag false dan route GET-only dipertahankan. Query di-whitelist, status divalidasi per jenis dan ID dicari dalam koleksi jenisnya. Hasil kosong menyediakan reset. Pemeriksaan otomatis dan review statis tidak membuktikan tampilan atau aksesibilitas browser; inventory browser sesi ini kosong.
