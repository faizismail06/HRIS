# Handoff inisialisasi — 20 Oktober 2026

Pengguna meminta fondasi dahulu agar dapat mengatur GitHub sebelum melanjutkan UI dengan model lain. UI keseluruhan belum dikerjakan pada tahap ini.

## Revisi terbaru: Ruang Tim

Pengguna menolak Fokus Tugas karena menu aktif hanya tiga dan meminta UI modern yang tidak generik. Shell kini navigasi horizontal tanpa sidebar, palet plum/putih-lavender tipis, kehadiran dan agenda berdampingan, daftar tugas lebar penuh di bawah. Ukuran teks dan hit area diperbesar. Filter dan detail inline tetap tersedia; tidak ada perubahan akses atau backend. Dasarkan shell pada jumlah menu yang aktif, bukan seluruh roadmap. Bagian Fokus Tugas di bawah adalah riwayat tahap sebelumnya. Build Vite, kompilasi Blade, enam tes/13 assertions dan HTTP 200 telah diperiksa ulang setelah revisi. Review statis menemukan status navigasi yang bisa tertinggal saat hash/history berubah; sudah diperbaiki dan diterima pada review perbaikan. Browser CUA tetap kosong sehingga tampilan desktop/mobile belum diverifikasi secara visual. Advisory detector mengenai palet/type muncul terhadap DESIGN.md lama dan ditangani melalui pembaruan dokumentasi desain yang diotorisasi pengguna.

## Pembaruan: dashboard dan navigasi

Pengguna selesai push GitHub dan meminta lanjut, lalu memilih dashboard HR/navigasi sebagai tahap berikut. Branch aktif `dev`. PHP sudah 8.5.11 dan Composer install/check-platform-reqs berhasil. Bagian inisialisasi di bawah merupakan catatan historis.

UI Fokus Tugas tersedia pada `/prototype/dashboard`: sidebar sage terang, ringkasan kehadiran, antrean yang bisa dicari/difilter, detail inline, agenda, dan navigasi mobile. Fixture hanya baca di `app/Support/PrototypeDashboard.php`; tidak ada koneksi ke model bisnis atau aksi keputusan. Flag `HRIS_UI_PREVIEW_ENABLED` harus true, lingkungan local/testing; production tetap ditolak saat flag aktif. `.env` lokal menggunakan session/cache file agar pratinjau tidak membutuhkan PostgreSQL. Konfigurasi DB default tetap PostgreSQL.

Pemeriksaan tahap UI: build Vite, kompilasi Blade, PHP syntax, Composer platform/validate, Pint, HTTP 200 dan 6 tes (13 assertions) berhasil. Detector desain mengembalikan `[]`. Browser CUA tidak tersedia (inventory kosong); screenshot desktop/mobile dan pengujian interaksi browser belum dilakukan. Tidak ada klaim kelulusan aksesibilitas atau visual. Login, izin, domain R0 dan instalasi demo terisolasi masih pekerjaan lanjutan.

## Kondisi proyek

- Scaffold resmi `laravel/laravel` v13.0.0 di root HRIS, Laravel dan Livewire dikunci per patch di composer.lock.
- Blade, Tailwind/Vite bawaan, PostgreSQL, locale `id` dan fallback `en`.
- Git branch `main`; remote, commit awal dan push belum dibuat.
- PRD tetap utuh, PRODUCT.md merangkum konteks, salinan selector ada di docs/frontend-skill-selector.md.
- Halaman default Laravel dipertahankan. Auth, izin, demo, dan seluruh modul bisnis belum diimplementasikan.

## Runtime

PHP PATH saat inisialisasi adalah 8.2.30. Laravel 13 dan PHPUnit 12 memerlukan PHP 8.3+. Composer hanya me-resolve lockfile memakai config.platform.php=8.3.0, belum memasang atau menjalankan paket PHP. Ini tidak membuat aplikasi kompatibel dengan PHP 8.2. Gunakan runtime sesuai lalu jalankan composer install dan composer check-platform-reqs.

PostgreSQL belum dihubungkan, migrasi dan tes PHP belum dijalankan. Gunakan database terpisah hris_testing untuk pengujian. Flag demo false belum merupakan implementasi isolasi/gate DEMO-01–06.

## Pemeriksaan selesai

- composer validate --strict: valid.
- npm install: berhasil, audit melaporkan 0 vulnerabilities saat inisialisasi.
- npm run build: berhasil (Vite 7.3.7).
- Pengecualian Git untuk .env, .env.testing, vendor, node_modules dan public/build diverifikasi.
- Tidak ada validasi browser/UI HRIS karena UI belum dibangun. Tes PHP tertunda sampai runtime sesuai tersedia.

## Skill dan kelanjutan

Skill sumber di `C:/Users/Faiz Ismail M/.agents/skills/`. Folder impeccable, make-interfaces-feel-better, transitions-dev, transitions-polish dan GSAP sejajar. Jika pindah mesin, ikuti lokasi yang diberikan sesi baru. Salinan selector tidak memasang skill asli.

Pilih impeccable untuk desain UI operasional (mode Operate). SKILL.md asli dan referensi init sudah dibaca. Launcher context gagal karena cache engine belum tersedia dan lokasi cache pengguna tidak dapat ditulis; Markdown skill berhasil dibaca dan konteks PRD dipakai langsung. PRODUCT.md tersedia, belum ada DESIGN.md atau arah visual yang disepakati.

Setelah pengguna mengatur GitHub dan meminta lanjut, periksa runtime, baca PRD dan selector, tetapkan arah desain, lalu bangun UI sesuai prioritas R0–R3. Baca referensi skill sebelum edit UI. Tambahkan transitions-dev jika interaksi membutuhkan, polish setelah UI ada. GSAP hanya jika kebutuhan animasi mendukung. Hindari menggandakan Alpine bawaan Livewire.

Navigasi mengikuti bagian 17 PRD, izin dan status rilis. Modul belum tersedia disembunyikan dari pekerjaan sehari-hari; mockup harus jelas berstatus prototipe, tanpa mengklaim keamanan atau data operasional telah berfungsi.
