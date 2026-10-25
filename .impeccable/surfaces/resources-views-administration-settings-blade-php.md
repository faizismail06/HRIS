---
version: 1
slug: "resources-views-administration-settings-blade-php"
primary_target: "resources/views/administration/settings.blade.php"
related_targets: ["resources/views/administration/audit.blade.php","resources/views/administration/imports.blade.php","resources/views/administration/import.blade.php"]
---

# Pengaturan, Audit & Impor

Scope: `/prototype/settings`, `/prototype/audit`, `/prototype/imports`, `/prototype/imports/{batch}`. Mode Operate. Dunia Ruang Tim, fixture sintetis baca saja.

THESIS: HR memahami rancangan mandat, melihat alasan perubahan/penolakan dan memperbaiki masalah impor sebelum ada perubahan data.

OWN-WORLD: palet plum, Segoe UI, native GET forms/disclosures, tabel semantik, panel putih dan focus ring incumbent. Global tujuh tujuan tetap; Administrasi kini delapan tautan lokal yang dapat membungkus. Tidak ada sidebar, raster atau motion baru.

STORY: baca matriks peran target, filter peristiwa audit, buka konteks sebelum/sesudah/alasan, kemudian telusuri batch impor. Detail mempertahankan filter kembali; lintas jenis tidak membawa q/status yang berbeda makna. Tidak ada perubahan peran atau commit dari UI.

FIRST VIEWPORT: judul dan status baca saja, navigasi lokal, lalu matriks/register. Detail impor mendahulukan hasil skenario dan hash, diikuti pemetaan dan baris bermasalah dengan kolom/alasan/perbaikan. Signature interaction: satu baris valid tetap tidak diterapkan bila batch memuat kesalahan; retry payload identik mengacu batch asal tanpa duplikasi.

FORM: perluasan sistem UI operasional. Label menegaskan peran/kebijakan belum diterapkan dan audit bukan log nyata. Kontrol pencarian/filter berfungsi GET; tabel memiliki caption/scope dan region scroll fokusable. Tanpa tombol unggah/simpan/commit palsu.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Fixture dan batas

Sembilan peran dari PRD dengan scope/batas; targetdefaultdeny, larangan selfapproval, duaaktor payroll, MFA dan delegasi masih kebutuhan backend. Pengaturan tidak membaca `.env`, kredensial atau konfigurasi rahasia.

Enam peristiwa audit menggambarkan scopechange, selfapprovaldenied, ekspor ditolak, validasi impor gagal, pengenalan retry dan closed snapshot. Aktor, sebelum/sesudah, korelasi dan alasan adalah data sintetis; tidak ada logger aktif, IP, rekening atau payload medis.

Empat batch: IMP-001 tiga baris, dua baris bermasalah, seluruh batch ditolak; formula '=2+3' tampil sebagai teks dan tidak dieksekusi. IMP-002 roster lintas tengah malam siap ditinjau, belum dipublikasikan. IMP-003 contoh selesai dua baris saldo; IMP-004 payload/jenis/hash sama dan mengacu asal, nol baris diterapkan dalam skenario. Semua penulisan nyata oleh UI tetap nol. Hash fixture bukan bukti atomisitas atau idempotency backend. Batas5.000baris/10MB adalah rancangan PRD, bukan uploadlimit yang sudah diberlakukan. Tidak ada parserCSV/XLSX, fileunggahan, ekspor atau macro handling aktif; modul tidak membuat/mengedit spreadsheet.

## Verifikasi

Gate preview local/testing, flagdefaultfalse dan GET-only dipertahankan. Filter whitelist, hasil kosong/reset dan unknown ID404. Review browser dan aksesibilitas belum dapat disahkan; katalog ini tidak dianggap acceptance domain.
