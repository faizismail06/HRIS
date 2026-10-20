# Handoff inisialisasi — 20 Oktober 2026

## Modul terbaru: Jadwal & Kehadiran

Preview GET-only tersedia pada `/prototype/attendance`, `/prototype/schedules`, dan `/prototype/attendance/{record}`. `PrototypeAttendance` menyediakan 70 baris sintetis untuk lima karyawan, 12–25 Oktober 2026, pada tiga cabang WIB. Snapshot tetap 20 Oktober 2026 pukul 08.45. Filter GET memakai whitelist dan batas tanggal; perpindahan tab serta tautan kembali mempertahankan karyawan, cabang, dan periode asal. Menu global kini empat tujuan dengan tambahan Kehadiran, tetap tanpa sidebar.

Nadia pada shift malam 19 Oktober pulang pada 20 Oktober, tetap satu tanggal kerja 19 Oktober. Status Hadir tetap disertai terlambat 12 menit dan pulang awal 10 menit; durasi contoh 398 menit setelah istirahat 60 menit. Nadia/Raka pada 18 Oktober belum lengkap; Dimas pada 19 Oktober libur; Fajar pada 19 Oktober cuti; percobaan lokasi Dimas pada 15 Oktober ditolak dan hasil akhirnya Tidak hadir; Dimas pada 20 Oktober sedang bekerja. Shift mendatang berstatus Belum mulai. Tidak ada engine operasional, pengambilan GPS/kamera/foto, publikasi roster, pengajuan koreksi atau approval. Durasi bukan otomatis lembur terbayar.

Build, Pint, kompilasi Blade, HTTP, serta 21 tes dengan 140 assertions lulus. Review statis tidak menemukan penghambat kode. Browser CUA tetap tidak tersedia, sehingga tampilan dan interaksi desktop/mobile belum diverifikasi. Detector hanya menghasilkan advisory skala desain: enam warna lama, 21 ukuran font dan empat radius; drift di luar scope tidak diperbaiki. `docs/attendance-surface.md` mencatat kontrak dan batas fixture. Bagian di bawah adalah riwayat tahap sebelumnya.

## Modul terbaru: Karyawan & Organisasi

UI baca saja tersedia pada `/prototype/employees`, `/prototype/employees/{employee}`, `/prototype/organization`. Fixture `PrototypePeople` berisi 12 profil, 6 unit termasuk Direksi, 3 cabang. Filter/sort GET menggunakan whitelist validasi Laravel. Profil menampilkan penugasan, relasi atasan/laporan langsung, status dan timeline sintetis. Organisasi menghitung ACTIVE+OFFBOARDING, mengabaikan PREBOARDING/ENDED pada jumlah bekerja namun tetap menyimpan profil di direktori.

Shell diekstrak ke `components/app-layout.blade.php`. Menu global tetap tiga tanpa sidebar: Ringkasan, Karyawan, Organisasi. Tugas/Agenda menjadi tautan lokal dashboard; highlight global dari route server, bukan hash. Prototipe tetap fail-closed dengan middleware/flag sebelumnya. Tidak ada mutasi atau integrasi database. Filter direktori dipertahankan saat berpindah ke profil atasan/laporan langsung lalu kembali; regresi ini sudah diuji dan diperiksa ulang. Daftar 12 profil dan agregat dashboard adalah fixture terpisah, dijelaskan di footer.

Build, Pint, kompilasi Blade, 14 tes/67 assertions dan HTTP terhadap dashboard/direktori/organisasi/profil berbagai status berhasil. Verifikasi browser tetap belum tersedia; jangan mengklaim UI/aksesibilitas teruji dari sumber saja. Detector menghasilkan advisory nilai desain yang sebagian sudah ada sebelum modul; tidak dilakukan perbaikan drift di luar scope.

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
