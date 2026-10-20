# Integrated Operational HRIS

Fondasi proyek berdasarkan [PRD Indonesia](PRD_HRIS_ID.md) dan [PRD English](PRD_HRIS_EN.md). Saat ini tersedia prototipe dashboard, direktori/profil karyawan, struktur organisasi, jadwal/kehadiran, pengajuan/cuti, kalender tim serta administrasi kontrak/surat/karier dengan navigasi **Ruang Tim** dan data sintetis. Autentikasi, otorisasi operasional, demo terisolasi dan aturan bisnis R0/R1 belum diimplementasikan.

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

Buka [dashboard lokal](http://127.0.0.1:8000/prototype/dashboard). Halaman utama mengarah ke dashboard hanya saat preview aktif. Navigasi atas memuat Ringkasan, Karyawan, Organisasi, Kehadiran, Pengajuan dan Administrasi; Tugas dan Agenda adalah bagian lokal dashboard. Menu berpindah ke baris sendiri pada lebar 1100 px ke bawah dan dapat membungkus, tanpa sidebar. Pencarian/filter dan detail tetap tersedia. Semua beroperasi pada fixture baca saja; tidak ada keputusan persetujuan atau penyimpanan data HR. Tanggal snapshot tetap 20 Oktober 2026.

## Modul Karyawan & Organisasi

- [Direktori](http://127.0.0.1:8000/prototype/employees): 12 profil sintetis, pencarian nama/nomor/jabatan, filter status/departemen/cabang, urutan nama/tanggal bergabung dan pemulihan hasil kosong. Filter memakai GET sehingga berfungsi tanpa JavaScript dan bisa dibagikan sebagai URL lokal.
- Profil: identitas kerja, penugasan, atasan/laporan langsung dan timeline contoh. Kembali ke direktori mempertahankan filter. Contoh Nadia (`EMP-004`) memisahkan jabatan saat ini dari perubahan 1 November; Maya (`EMP-006`) belum bergabung; Raka (`EMP-009`) offboarding; Intan (`EMP-011`) berakhir.
- [Organisasi](http://127.0.0.1:8000/prototype/organization): induk Direksi dan lima unit di bawahnya, penanggung jawab, anggota bekerja serta filter cabang. Jumlah anggota hanya mencakup Aktif dan Offboarding. Profil Pra-bergabung/Berakhir tetap tersedia di direktori.

Tidak ada create/edit/delete, pengaturan akses, data sensitif atau dokumen nyata. Ini belum memenuhi acceptance CoreHR R0. Cuplikan 12 profil terpisah dari agregat sintetis dashboard, bukan satu basis data operasional. Tabel direktori mempertahankan kolom dan dapat digeser horizontal pada layar kecil.

## Modul Jadwal & Kehadiran

- [Rekap kehadiran](http://127.0.0.1:8000/prototype/attendance): filter tanggal kerja, karyawan, cabang dan status. Ringkasan menjelaskan cakupan sebelum filter status. Terlambat dan pulang awal tetap terpisah dari status Hadir.
- [Jadwal mingguan](http://127.0.0.1:8000/prototype/schedules): dua minggu contoh 12–18 dan 19–25 Oktober 2026, lima karyawan, tiga cabang WIB; hari libur per roster, shift pagi/siang/malam/kantor dan tautan detail setiap hari.
- Detail shift: jadwal dan tanggal pulang berikutnya, clock-in/out sintetis, interval istirahat, durasi contoh, jejak percobaan ditolak, definisi status dan panduan koreksi baca saja. Tautan kembali mempertahankan scope asal; tab rekap/jadwal mempertahankan karyawan/cabang.

Skenario penting: Nadia19 malam tetap hari kerja19 walau pulang20Okt (terlambat12, pulangawal10, durasi398menit); Nadia18 belum lengkap; Dimas19 libur, Fajar19 cuti, Dimas15 percobaan lokasi ditolak, Dimas20 sedang bekerja. Semua peristiwa berlabel SIMULATED, snapshot20Okt08.45WIB. Tidak ada pengambilan GPS/kamera, pengiriman koreksi, penilaian geofence nyata, publikasi roster atau clocking operasional. Fixture ini tidak memenuhi acceptance attendance R1.

## Modul Cuti & Pengajuan

- [Daftar pengajuan](http://127.0.0.1:8000/prototype/requests): delapan contoh cuti, koreksi absensi, lembur dan tukar shift. Filter nomor/nama, jenis, status, cabang dan karyawan; detail menampilkan pemilik tahap, tenggat, usia sejak pengajuan, keputusan dan tahap menunggu/selesai.
- [Saldo cuti](http://127.0.0.1:8000/prototype/leave-balances): ledger per profil sintetis dengan kredit, debit, reservasi dan pelepasan. Nadia memiliki kredit 12 hari, debit 3 dan reservasi 2, tersisa 7 hari. Arif memiliki saldo tersedia 9,5 hari termasuk reservasi 0,5 hari; penolakan Dimas melepas reservasi tanpa pemakaian final.
- [Kalender tim](http://127.0.0.1:8000/prototype/team-calendar): roster dan ketidaktersediaan disetujui per minggu/cabang. Hanya Terjadwal/Tidak tersedia/Libur yang ditampilkan; tidak ada jenis cuti pribadi, diagnosis atau alasan pengajuan.

Jumlah hari dan keputusan di atas seluruhnya ilustrasi, bukan hak cuti atau ketentuan hukum. Approved dates menggunakan satu sumber fixture untuk rekap dan kalender (Fajar 19 Oktober, Nadia 23 Oktober); pengajuan tertunda tidak mengubah hasil final. Koreksi Nadia belum mengubah raw event, rencana lembur belum berarti pembayaran, dan pertukaran yang ditarik tidak mengubah roster. Tidak ada submit, approve/reject, cancel, notifikasi atau transaksi saldo yang dikirim oleh UI. Ini belum memenuhi acceptance workflow/leave R1.

Preview ditolak di luar lingkungan local/testing, bahkan bila flag aktif. Flag default false pada `.env.example`; pratinjau ini bukan implementasi instalasi demo DEMO-01. UI dirancang responsif; verifikasi browser desktop/ponsel masih perlu dilakukan karena browser tidak tersedia pada sesi implementasi.

## Modul Kontrak, Surat & Perubahan Karier

- [Kontrak](http://127.0.0.1:8000/prototype/contracts): enam versi/periode aktif, terjadwal, tinjauan dan arsip. Detail kontrak aktif menampilkan tanggal pengingat contoh 60/30/14/7 hari sebelum akhir periode; tanpa pengiriman atau perpanjangan otomatis.
- [Surat](http://127.0.0.1:8000/prototype/letters): empat snapshot termasuk versi asli, penerbitan ulang bernomor berbeda, surat pengalaman setelah periode kerja selesai dan draf tanpa nomor final. Pratinjau HTML memakai nilai tersimpan per versi, dengan cap data sintetis.
- [Perubahan karier](http://127.0.0.1:8000/prototype/career-changes): nilai sebelum/sesudah dan tanggal efektif. Promosi Nadia mulai November dan usulan cabang Sinta tidak mengubah profil hari ini; perubahan atasan Arif sudah efektif pada snapshot.

Semua register memiliki filter GET serta detail yang mempertahankan konteks kembali. Filter cabang merujuk profil saat ini, bukan cabang snapshot. Perpindahan jenis catatan hanya membawa cabang/karyawan. Tidak ada unggahan, PDF, unduhan, tanda tangan, penerbitan, penomoran unik, mesin tanggal efektif, notifikasi atau penyesuaian periode tertutup operasional. Ini belum memenuhi acceptance employment R1.

## Modul Onboarding, Offboarding & Aset

- [Siklus kerja](http://127.0.0.1:8000/prototype/lifecycle): empat checklist per periode dengan pemilik tugas, tenggat, ketergantungan, kewajiban dan referensi bukti sintetis. Ada onboarding Maya, offboarding Raka, clearance Intan setelah akhir kerja dan arsip bergabung kembali Fajar.
- [Aset dan barang](http://127.0.0.1:8000/prototype/assets): lima aset serta satu barang habis pakai. Detail membedakan calon penerima, pemegang aktif, pengembalian terdahulu dan kasus tinjauan tanpa potongan gaji otomatis.

Laptop Intan tetap menjadi tugas terbuka meskipun akses aplikasi sudah dicabut dalam skenario. Reservasi Maya belum menjadi serah terima; barang habis pakai tidak perlu dikembalikan. Clearance periode terdahulu Fajar tetap terpisah. Filter dan tautan detail memakai GET, tanpa menutup tugas atau mengubah akses. Tidak ada lampiran nyata, scheduler, integrasi IT eksternal atau transaksi inventaris; ini belum memenuhi acceptance lifecycle/assets R2.

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

Tes database menggunakan PostgreSQL hris_testing pada phpunit.xml; isi kredensial melalui .env.testing yang diabaikan Git. Tes saat ini mencakup scaffold dan perilaku prototipe UI, termasuk gate produksi, filter, riwayat contoh dan navigasi profil; bukan penerimaan domain R0. Scheduler dapat dijalankan dengan php artisan schedule:work saat fitur terjadwal tersedia.

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
