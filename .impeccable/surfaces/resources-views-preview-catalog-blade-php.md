---
version: 1
slug: "resources-views-preview-catalog-blade-php"
primary_target: "resources/views/preview/catalog.blade.php"
related_targets: ["resources/views/preview/form.blade.php","resources/views/preview/portal.blade.php","resources/views/preview/state.blade.php","resources/views/preview/_form-fields.blade.php","resources/views/components/static-auth-layout.blade.php"]
---

# Penyelesaian Visual Statis

Scope: `/prototype/screens`, `/prototype/screens/{screen}`, `/prototype/states/{state}`, `/prototype/portals/{persona}` dan komponen layout bersama. Mode Operate, extension dunia Ruang Tim.

THESIS: Seluruh kelompok layar inti PRD dapat ditinjau dari satu katalog, dengan perbedaan tegas antara gambar keadaan UI dan fungsi operasional.

OWN-WORLD: Segoe UI, plum, panel putih, native controls, tabel/disclosures dan focus ring incumbent. Tidak ada sidebar, raster atau library baru. Default global tujuh tujuan dipertahankan; portal memiliki contoh navigasi peran. Tautan panduan ada di baris utilitas bersama pencarian/notifikasi. Auth memakai shell ringkas tanpa navigasi HR. Form dua kolom menjadi satu pada <=760px.

STORY: buka katalog, pilih satu dari tujuh beranda peran, telusuri 48 formulir dalam kondisi awal/validasi gagal, lalu dua belas state umum. Katalog menautkan daftar/detail terdahulu dan tiga rute pemeriksaan lintas modul. Tautan tersebut bukan transaksi end-to-end tersambung.

FIRST VIEWPORT: judul panduan dan anchor bagian, diikuti peran lalu daftar modul. Form mendahulukan batas statis dan pilihan kondisi sebelum fieldset disabled; kesalahan pertama disertai pesan spesifik sementara nilai contoh lainnya bertahan. State akses/expiry tidak menampilkan payload catatan. Signature interaction: berganti pratinjau awal/invalid melalui GET sambil melihat bidang contoh yang tetap sama; tidak ada data dikirim.

FORM: extension sistem yang disetujui. Portal Karyawan memakai Nadia/EMP-004, libur20Okt dan shift berikut21Okt; Manager Bima hanya laporan langsung bekerja serta tahap aktifnya; Finance menampilkan gagal/sebagian; IT, Auditor dan Platform menampilkan mandatnya. Perubahan persona hanya tampilan, bukan autentikasi atau otorisasi. Tidak ada tombol simpan, reset, login, upload atau GPS palsu yang aktif.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Cakupan dan batas

48 form mencakup CoreHR/lokasi/profil/periode, roster/clock/koreksi/cuti/lembur aktual/swap/approval/delegasi, kontrak/template/surat/karier, lifecycle/tugas/aset, komponen/run/finalisasi/rilis slip/bank/rekonsiliasi/klaim/penyesuaian, close/reopen/reportexport, akun/workflow/impor/preferensi, login/MFA/recovery/reset/invite dan demo. Fieldset dinonaktifkan, tanpa nama form atau submit endpoint; password kosong dan file tidak diterima. Primary copy mengikuti tujuan, auth tidak memiliki simpan draf. Lihat inventaris lengkap di docs/STATIC_SCREENS.md.

State: empty, loading statis, error dengan input contoh, forbidden, stale V1/V2, queued, konfirmasi sukses contoh, offline, izin kamera/lokasi ditolak, sesi/tautan expired, not-found dan konfirmasi reset berbatas skenario. Skeleton tidak bergerak dan tidak berpura-pura ada permintaan aktif. Kontrol konfirmasi reset dinonaktifkan.

Tidak ada login, role switching nyata, acceptance credential, autosave, unggahan, operasi data, PDF, ekspor, pembayaran, akses perangkat, seed/reset atau perubahan jam server. Navigasi peran bukan policy. Angka dan rate regulasi tidak diciptakan. Backend tetap belum tersedia.

## Verifikasi

Route berada di gate local/testing + flag preview dan GET-only. Setiap form pada dua kondisi, tujuh portal dan dua belas state dirender melalui tes HTTP. Invalid variants/IDs ditolak. Browser IAB dicoba langsung tetapi ditolak 'Browser is not available: iab'; tidak ada screenshot atau pengesahan visual/aksesibilitas browser. Cakupan implementasi statis dan kelulusan QA visual dipisahkan dalam status proyek.
