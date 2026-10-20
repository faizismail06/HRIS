# Integrated Operational HRIS

Fondasi proyek berdasarkan [PRD Indonesia](PRD_HRIS_ID.md) dan [PRD English](PRD_HRIS_EN.md). Saat ini tersedia prototipe dashboard HR dan navigasi **Ruang Tim** dengan data sintetis. Autentikasi, otorisasi operasional, demo terisolasi dan modul bisnis belum diimplementasikan.

## Pratinjau dashboard tanpa database

Dependensi PHP dan frontend telah dipasang pada mesin pengembangan. Untuk instalasi baru, jalankan `composer install` dan `npm ci`. Salin `.env.example` ke `.env` jika belum ada, lalu atur:

```dotenv
APP_ENV=local
HRIS_UI_PREVIEW_ENABLED=true
SESSION_DRIVER=file
CACHE_STORE=file
```

```powershell
php artisan key:generate
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [dashboard lokal](http://127.0.0.1:8000/prototype/dashboard). Halaman utama mengarah ke dashboard hanya saat preview aktif. Navigasi atas memuat Ringkasan, Tugas dan Agenda. Kehadiran dan agenda berdampingan pada desktop, lalu bertumpuk pada ponsel. Pencarian tugas/karyawan, filter kategori/cabang/prioritas dan detail inline tetap tersedia. Semua beroperasi pada fixture baca saja; tidak ada keputusan persetujuan atau penyimpanan data HR. Tanggal snapshot tetap 20 Oktober 2026.

Preview ditolak di luar lingkungan local/testing, bahkan bila flag aktif. Flag default false pada `.env.example`; pratinjau ini bukan implementasi instalasi demo DEMO-01. UI dirancang responsif; verifikasi browser desktop/ponsel masih perlu dilakukan karena browser tidak tersedia pada sesi implementasi.

## Stack

Laravel 13 + Livewire 4, Blade, Tailwind CSS 4, Vite dan PostgreSQL. Versi patch dikunci di composer.lock dan package-lock.json.

PHP 8.3+ beserta ekstensi Laravel dan pdo_pgsql diperlukan. Gunakan Node.js 22.12+ atau versi kompatibel Vite 7. Composer config.platform.php=8.3.0 menetapkan target resolusi dependensi, bukan memperbarui runtime lokal.

## Setup lokal (PowerShell)

Pilih PHP 8.3+ dan buat database PostgreSQL hris_local serta hris_testing. Jangan gunakan database operasional untuk tes.

```powershell
composer install
composer check-platform-reqs
Copy-Item .env.example .env
# Isi kredensial PostgreSQL pada .env sebelum migrasi.
php artisan key:generate
php artisan migrate
npm ci
npm run build
composer dev
```

Lewati Copy-Item jika .env sudah ada. Queue awal menggunakan database; Redis dapat dikonfigurasi nanti. Email lokal memakai log, penyimpanan default privat, demo nonaktif. Flag demo belum memiliki implementasi simulator.

## Pemeriksaan

```powershell
composer validate --strict
php artisan test
vendor/bin/pint --test
npm run build
```

Tes database menggunakan PostgreSQL hris_testing pada phpunit.xml; isi kredensial melalui .env.testing yang diabaikan Git. Tes bawaan adalah smoke test scaffold, bukan penerimaan R0. Scheduler dapat dijalankan dengan php artisan schedule:work saat fitur terjadwal tersedia.

## Hubungkan GitHub

Repository sudah terhubung ke GitHub. Pengembangan dilakukan pada branch `dev`. Setiap modul yang selesai dan sudah diperiksa dibuatkan commit lalu di-push ke `dev`, sesuai instruksi pengguna. Target riwayat proyek minimal 10 commit, dibagi berdasarkan perubahan yang bermakna; jangan membuat commit kosong atau memecah perubahan semata-mata untuk mengejar jumlah. Jangan force-push atau mengubah `main` tanpa instruksi pengguna.

Untuk referensi instalasi repository baru:

```powershell
git add .
git commit -m "chore: initialize HRIS foundation"
git remote add origin https://github.com/USERNAME/REPOSITORY.git
git push -u origin main
```

Ganti URL sesuai repository Anda; jangan menambahkan ulang remote yang sudah ada. Commit kedua lockfile; .env, dependensi dan build dikecualikan. Jika muncul dubious ownership, verifikasi kepemilikan folder dan tambahkan hanya folder ini ke safe.directory pada akun Windows Anda.

## Lanjutan

Baca [PRODUCT.md](PRODUCT.md), [handoff](docs/HANDOFF.md) dan [frontend skill selector](docs/frontend-skill-selector.md) sebelum melanjutkan UI. R0–R3 adalah roadmap. Metadata lisensi MIT berasal dari scaffold Laravel; lisensi produk belum ditetapkan.
