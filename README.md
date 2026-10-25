# Integrated Operational HRIS

HRIS lokal berbasis Laravel untuk mengelola identitas akun, karyawan, organisasi, pengajuan, kehadiran, roster, cuti, lembur, dokumen, lifecycle, aset, payroll draft, reimbursement, impor, laporan, audit, notifikasi, dan pencarian berizin.

Antarmuka operasional berada di `/app/*`. Ia memakai navigasi horizontal tanpa sidebar. Rute `/prototype/*` adalah katalog visual terpisah dan tidak menyimpan transaksi.

## Status saat ini

Domain dan formulir operasional telah tersedia di aplikasi lokal, dengan pemeriksaan izin, MFA, scope data, workflow, audit, idempotensi, dan versi data pada server. Halaman yang sudah mendapat perapian visual khusus adalah ruang kerja, direktori karyawan, organisasi, rekap kehadiran, serta daftar dan detail roster. Halaman domain lain memakai shell dan komponen formulir yang sama; perapian visual rinci per halaman dapat diteruskan tanpa mengubah domainnya.

Payroll tetap berupa konfigurasi draft. Kebijakan prorata, lembur, pajak/BPJS, dan format ekspor bank belum ditetapkan perusahaan sehingga finalisasi payroll sengaja tetap terkunci.

Dokumentasi operasional internal disimpan lokal dan tidak diterbitkan bersama repository portfolio ini.

## Menjalankan secara lokal

### Prasyarat

- PHP 8.3 atau lebih baru dengan ekstensi PostgreSQL (`pdo_pgsql` dan `pgsql`)
- Composer dan Node.js/npm
- PostgreSQL

Instal dependensi dan buat konfigurasi lokal:

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Atur koneksi PostgreSQL pada `.env`, lalu jalankan migrasi dan aset frontend:

```powershell
php artisan migrate
npm run build
composer run dev
```

`composer run dev` menjalankan server Laravel, queue worker, scheduler, log viewer, dan Vite. Buka `http://127.0.0.1:8000`.

Untuk menjalankan hanya server aplikasi, gunakan:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## Akun pertama dan login

Repository tidak berisi akun, password, maupun data portfolio bawaan. Buat akun pertama secara interaktif setelah migrasi:

```powershell
php artisan hris:provision-account platform_admin --bootstrap --company="Nama Perusahaan"
```

Perintah ini tetap menjadi cara resmi untuk bootstrap perusahaan baru. Setelah itu, buat akun HR melalui `hris:provision-account hr_admin` atau `hr_officer` sesuai kebutuhan. Platform Admin mengelola akses akun dan tidak otomatis memperoleh akses data karyawan.

Untuk pengujian lokal tanpa challenge dua langkah, tambahkan ini ke `.env`:

```dotenv
HRIS_LOCAL_SKIP_MFA=true
```

Kemudian jalankan `php artisan config:clear`. Pengecualian ini hanya berlaku pada environment `local` non-demo; password dan izin tetap diperiksa. Kembalikan ke `false` untuk mengaktifkan MFA kembali.

Seeder akun dan data portfolio bersifat lokal, diabaikan Git, dan tidak tersedia pada clone baru. `DatabaseSeeder` hanya akan memanggil paket seeder lokal opsional bila file tersebut memang ada. Jangan mengandalkan `php artisan db:seed` untuk membuat akun pada instalasi baru; gunakan provisioning interaktif di atas.

## Peta aplikasi

| Area | Rute utama | Keterangan |
| --- | --- | --- |
| Ruang kerja | `/app` | Ringkasan berizin, antrean review, pintasan kerja, dan panduan organisasi kosong. |
| Karyawan & organisasi | `/app/employees`, `/app/organization` | Direktori, penempatan, perubahan efektif, cabang, departemen, dan jabatan. |
| Pengajuan | `/app/requests` | Draf, unggah bukti privat, kirim, tarik, dan keputusan independen. |
| Kehadiran | `/app/attendance`, `/app/schedules` | Clocking, detail kehadiran, roster, pengganti roster, dan publikasi. |
| Cuti & lembur | `/app/leave-*`, `/app/overtime`, `/app/attendance-periods` | Saldo/ledger, kredit, entitlement, aktual lembur, dan penutupan periode. |
| Alur lanjutan | `/app/modules` | Formulir dan tindakan domain lain, termasuk dokumen, lifecycle, aset, finance, impor, laporan, administrasi, dan demo terisolasi. |

Mandat, scope, validasi, dan proses latar belakang diterapkan pada controller serta layanan domain di aplikasi.

## Pengujian dan pemeriksaan

```powershell
php artisan test
npm run build
php artisan view:cache
php vendor/bin/pint --test
```

Suite menggunakan database pengujian terpisah. Jangan menjalankan `migrate:fresh` pada database yang ingin dipertahankan. Pemeriksaan spesifik kehadiran dan roster:

```powershell
php artisan test --compact --filter='AttendanceWorkspaceTest|CalendarReplacementWorkspaceTest|CloseOvertimeWorkspaceTest'
```

Untuk penggunaan lokal, jalankan aplikasi dengan `composer run dev`. Kebutuhan deployment tidak termasuk cakupan repository portfolio ini.
