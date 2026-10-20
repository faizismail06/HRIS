# Karyawan dan Organisasi

Scope: direktori `/prototype/employees`, profil `/prototype/employees/{employee}`, struktur `/prototype/organization`. Mode Operate. Perluasan UI Ruang Tim; bukan implementasi backend CoreHR/R0.

## Direction contract

THESIS: HR menemukan seseorang, memahami penugasannya, dan menelusuri hubungan tim tanpa mengubah catatan kerja.

OWN-WORLD: mewarisi plum, white surfaces, fine dividers, Segoe UI, radius dan focus styles Ruang Tim. Tidak menambah identitas visual, raster, dependensi, sidebar atau menu yang belum tersedia.

STORY: filter direktori, buka profil, kembali dengan filter yang sama; lihat status hubungan kerja dan timeline terjadwal; telusuri struktur perusahaan dan anggota per cabang. Semua fixture berlabel sintetis.

FIRST VIEWPORT: shared header dengan tiga menu global Ringkasan/Karyawan/Organisasi. Direktori: judul, status links, native GET filters dan tabel profil. Profil: identitas ringkas, assignment fields dan history, hubungan tim di bawah. Organisasi: satu panel, induk Direksi dan native disclosure per unit. Signature interaction: tautan profil mempertahankan filter direktori dan hubungan tim dapat ditelusuri melalui profil terkait.

FORM: extension dunia Ruang Tim yang sudah dipilih pengguna; komposisi mengikuti kebutuhan direktori dan hierarki, tanpa putaran identitas baru.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Batas dan konten

12 profil contoh, 6 unit termasuk Direksi, 3 cabang, snapshot 20 Oktober 2026. Organisasi menghitung ACTIVE + OFFBOARDING saja; tidak menyertakan PREBOARDING/ENDED sebagai anggota bekerja. Kepala/penanggung jawab unit tetap ditampilkan ketika filter cabang mengubah anggota. Profil berakhir dan perubahan masa depan dipertahankan dengan label jelas. Tidak ada NIK, bank, tax, medical atau file upload.

Server GET filters bekerja tanpa JS; input di-whitelist/dibatasi dan kombinasi kosong memiliki jalur pemulihan. Tabel sengaja scroll horizontal dengan region berlabel/focusable pada ponsel agar relasi kolom tetap utuh. Route dan middleware preview tetap fail-closed di produksi. Tidak ada endpoint mutasi.

Browser CUA sebelumnya tidak tersedia. Build, kompilasi Blade, HTTP dan tes bukan bukti verifikasi visual/aksesibilitas. Daftar 12 profil adalah cuplikan sintetis terpisah dari agregat dashboard, dijelaskan di footer.
