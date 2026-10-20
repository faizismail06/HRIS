# Dashboard HR — Ruang Tim

Scope: `/prototype/dashboard`, mode Operate, prototipe baca saja dengan fixture sintetis. Pengguna meminta mengganti Fokus Tugas setelah melihat hanya tiga menu aktif, dan menghendaki UI modern dengan detail yang rapi. Arah Ruang Tim sudah diperkenalkan pada pilihan sebelumnya dan dipakai mengikuti koreksi pengguna. Jumlah menu aktif menjadi dasar navigasi, bukan jumlah modul roadmap.

## Direction contract

THESIS: Gambaran hari kerja yang seimbang, dengan tiga tujuan navigasi yang langsung terlihat dan ruang baca yang lebih lega.

OWN-WORLD: putih dengan ground lavender sangat tipis, plum untuk tindakan dan pilihan, tinta netral gelap, garis tipis, radius panel 14 px, font UI sistem. Warna kehadiran tetap semantik. Tanpa gradien, dekorasi foto, kartu statistik berulang, atau animasi pembuka.

STORY: baca kondisi tim dan agenda terlebih dahulu, lalu telusuri tugas dengan pencarian, filter, dan konteks inline. Data snapshot tetap sintetis, tidak ada kemampuan operasional baru yang diklaim.

FIRST VIEWPORT: header horizontal dengan brand, tiga menu, dan persona; judul sapaan dan tanggal; kehadiran dan agenda berdampingan. Daftar tugas memakai lebar penuh di bawah. Pada ponsel navigasi tetap terlihat dalam satu baris di bawah brand dan konten menjadi satu kolom. Signature interaction: membuka konteks tepat pada baris tugas sambil mempertahankan filter.

FORM: Ruang Tim, user-pinned correction pada putaran sebelumnya. Navigasi atas menggantikan sidebar untuk tiga menu aktif. Referensi seed historis: seed:80a88acf5b20d405; tidak ada pilihan acak baru yang mengalahkan arahan pengguna. Kantor terang menjadi dasar permukaan light.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Batas

Autentikasi, data model, gate preview dan backend tidak diubah. Tidak ada raster. Browser CUA belum tersedia; uji visual desktop/mobile tidak boleh diklaim lulus dari build atau review kode. Pertahankan no-JS fallback, native details, label filter, fokus dan reduced motion.
