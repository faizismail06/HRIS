# Onboarding, Offboarding & Aset

Scope: `/prototype/lifecycle`, `/prototype/assets` dan detail `/{record}`. Mode Operate. Perluasan Administrasi pada dunia Ruang Tim, data sintetis baca saja.

THESIS: HR mengetahui siapa yang belum menyiapkan tugas dan membedakan kewajiban aset terbuka dari status periode kerja maupun akses aplikasi.

OWN-WORLD: mewarisi Segoe UI, palet plum, panel putih, tabel semantik, native GET filters dan focus ring. Tidak ada sidebar, raster atau animasi baru. Header global tetap enam tujuan; Administrasi menambah tautan lokal Siklus kerja dan Aset.

STORY: filter checklist, buka periode kerja, periksa tugas wajib, pemilik, tenggat, ketergantungan dan referensi bukti contoh; ikuti aset terkait ke riwayat serah terima lalu kembali melalui register masing-masing. Tautan kembali mempertahankan filter. Lintas jenis hanya membawa cabang/karyawan; tautan kasus-aset memakai karyawan terkait.

FIRST VIEWPORT: judul/snapshot, navigasi lokal lalu register atau ringkasan periode. Detail checklist memakai tabel tugas; detail aset memisahkan inventaris, riwayat pemegang dan kasus tindak lanjut. Signature interaction: Intan sudah berakhir dan aksesnya dicabut dalam skenario, sementara pengembalian laptop tetap terlihat terbuka.

FORM: perluasan sistem UI yang disetujui. Tidak ada kontrol palsu untuk menutup tugas, aktivasi, pencabutan akun atau serah terima. Status tertulis, bukan hanya warna; tabel tetap fokusable dan dapat digeser pada layar sempit.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Fixture dan batas

Empat kasus: Maya pra-bergabung dengan verifikasi HR yang menghalangi penyiapan akun IT; Raka offboarding sampai 31 Oktober dengan hari kerja terakhir 30 Oktober dan contoh pencabutan 1 November 00.00 WIB; Intan berakhir dengan akses dicabut tetapi laptop belum kembali; Fajar memiliki checklist bergabung kembali 2024 dan arsip clearance periode terdahulu terpisah. Semua referensi bukti sintetis berupa teks, tanpa lampiran atau informasi pribadi asli. Alasan keluar yang bersifat sensitif tidak disertakan pada preview.

Lima aset dan satu barang habis pakai: reservasi bukan pemegang aktif; setiap riwayat memiliki maksimal satu pemegang aktif; barang habis pakai bukan pinjaman. Aset Intan yang belum kembali dan headset Sinta rusak memiliki kasus peninjauan tanpa tanggungan atau potongan gaji otomatis. Riwayat peralatan Fajar menyimpan pengembalian pemegang lama sebelum serah terima baru. Filter cabang pada aset memakai lokasi inventaris; pada siklus kerja memakai profil saat ini.

Tidak ada domain persistence, acceptance bukti, kontrol akses produksi, pengecualian HR operasional, scheduler, pencabutan sesi, integrasi akun eksternal, penghitungan penyelesaian finansial atau pencegahan bentrok transaksi aset. Akun IT eksternal adalah tugas manual berbukti dalam skenario. Modul belum memenuhi acceptance lifecycle/assets R2.

## Verifikasi

Preview tetap local/testing dengan flag default false. Semua route GET-only. Query divalidasi per register, ID tidak dikenal404, hasil kosong dengan reset. Browser belum tersedia pada tahap sebelumnya; automated checks dan review kode tidak membuktikan tampilan/aksesibilitas.
