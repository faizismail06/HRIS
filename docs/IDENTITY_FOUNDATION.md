# Fondasi identitas dan CoreHR

Pembaruan 23 Oktober 2026: seeder akun/data portfolio dan pengujiannya disimpan lokal serta diabaikan Git. Repository tidak menyediakan akun bawaan. Bootstrap/provisioning manual tetap tersedia; DatabaseSeeder melewati paket lokal yang tidak ada pada clone baru. Data dan akun yang sudah tersimpan di database pengembangan tidak dihapus. MFA mengikuti pengecualian testing lokal yang dipilih pengguna; lingkungan lainnya tetap mewajibkan MFA.

Modul backend pertama menyediakan login nyata melalui Laravel Fortify, MFA wajib untuk data operasional, pemulihan kata sandi, grants peran/scope, direktori identitas kerja baca saja dan migrasi PostgreSQL. Preview `/prototype/*` tetap memakai fixture sintetis, terpisah dari akun dan database operasional. Ini belum acceptance R0 atau aplikasi siap produksi.

## Menjalankan

PHP CLI pada mesin ini telah diverifikasi 8.5.11 dengan `pdo_pgsql` dan `pgsql` aktif. Paket tetap memakai batas versi PHP dalam `composer.json`. Database memerlukan PostgreSQL 15+ karena `UNIQUE NULLS NOT DISTINCT`; pengembangan memakai PostgreSQL 17. Migrasi menggunakan `btree_gist`, sehingga pengguna migrasi harus memiliki izin memasangnya atau ekstensi dipasang operator lebih dahulu.

Atur koneksi PostgreSQL privat pada `.env` dan pertahankan `APP_KEY`. Jangan menyalin kredensial ke Git. Pada instalasi baru:

```powershell
composer install
npm ci
php artisan migrate
npm run build
php artisan hris:provision-account platform_admin --bootstrap --company="Nama Perusahaan"
php artisan serve --host=127.0.0.1 --port=8000
```

Perintah provisioning meminta nama, email dan kata sandi tersembunyi dua kali. Minimal 12 karakter dengan huruf besar/kecil dan angka; maksimum 72 byte untuk bcrypt dan tanpa karakter null. Tidak menerima kata sandi di argumen, tidak membuat akun default, dan memerlukan terminal interaktif. Bootstrap hanya berlaku sebelum perusahaan pertama tersedia. Identitas akun nyata belum dibuat otomatis pada mesin pengguna.

Buka `/login`. Setelah password benar, akun baru diarahkan ke pengaturan MFA sebelum membuka data. Konfirmasikan ulang password, pindai QR atau gunakan kunci TOTP manual, kemudian masukkan OTP. Simpan kode pemulihan di tempat pribadi. Tidak ada pendaftaran publik. Admin platform mengelola akses, tetapi tidak otomatis dapat membaca data karyawan.

Operator terminal tepercaya dapat membuat akun HR, misalnya:

```powershell
php artisan hris:provision-account hr_admin
php artisan hris:provision-account hr_officer --scope=branch --target=1
php artisan hris:provision-account employee --scope=self --employee=1
php artisan hris:provision-account manager --scope=team --employee=2
php artisan hris:revoke-account 3 --actor=1
```

ID di atas hanya contoh dan harus diganti dengan catatan yang benar-benar tersedia. Master organisasi dan tambah/perubahan identitas kerja kini tersedia sesuai [COREHR_WORKFLOW.md](COREHR_WORKFLOW.md). Akun Employee/Manager harus ditautkan ke karyawan. Provisioning/revoke adalah tindakan operator dengan akses shell, bukan endpoint publik atau pengganti autentikasi admin di web. Argumen `--actor` mencatat penanggung jawab dan memvalidasi peran aktif, bukan membuktikan identitas operator shell.

## Batas akses

`/app` memerlukan akun aktif dan sesi yang telah melewati MFA. `/app/employees` serta detailnya memerlukan peran pembaca; query membatasi data sebelum mengambil detail. ID di luar scope menghasilkan 404. Pencarian tidak memperluas scope. Hanya identitas kerja, status hubungan kerja dan penempatan pada tanggal pilihan yang dikirim; tidak ada gaji, rekening, NIK, data medis atau dokumen.

| Peran | Akses direktori pada modul ini |
| --- | --- |
| Employee | Diri sendiri, grant `self` |
| Manager | Laporan langsung pada tanggal pilihan, grant `team` |
| HR Officer / HR Admin / Auditor | Scope company, branch atau department yang diberikan |
| Platform Admin / Payroll Officer / Finance Approver / IT Asset Officer | Tidak mendapat akses direktori dari peran tersebut |

Semua peran saat ini wajib MFA sebelum data operasional. Penambahan grants harus melalui layanan `AccountAccess`, dengan kebijakan default deny untuk mutasi karyawan. Perubahan grants/aktivasi menaikkan `security_version` dan membatalkan sesi lama; admin platform aktif terakhir dilindungi. Perubahan MFA/kode pemulihan dan reset password juga membatalkan sesi lain. Akun yang masa kerjanya berakhir ditolak pada request berikutnya. Tidak ada pekerjaan terjadwal pencabutan pada modul ini; pemeriksaan berlangsung saat request.

OTP diverifikasi provider Fortify, kode pemulihan diganti setelah dipakai, dan request identitas yang terautentikasi/menunggu MFA diserialisasi dengan row lock. Login dan challenge dibatasi lima percobaan per menit; mutasi identitas dibatasi 20 request per IP per menit. Konfirmasi password diwajibkan untuk pengaturan keamanan. Respons privat diberi `no-store`; secret dan recovery codes terenkripsi serta disembunyikan dari serialisasi User. Audit hanya menyimpan field yang dipilih, tanpa password, token reset, OTP atau recovery code.

## Aturan data

Satu perusahaan memiliki ID 1. Struktur departemen menolak siklus. Jabatan disimpan di `job_positions`, terpisah dari tabel antrean Laravel `jobs`. Penempatan harus memakai jabatan pada departemen yang sama dan berada dalam periode kerja karyawan tersebut. Periode kerja dan penempatan menggunakan rentang **[mulai, akhir)**: tanggal akhir eksklusif, sehingga rentang berurutan dapat bersinggungan di batas tanpa overlap. Periode yang tumpang tindih, self-manager dan siklus atasan pada rentang tanggal yang bertemu ditolak database. Pemendekan periode tidak boleh meninggalkan penempatan di luarnya.

Tanggal `as_of` dibatasi sampai hari ini dan scope mengikuti penempatan pada tanggal itu. Kalender aplikasi default `Asia/Jakarta`, dapat diatur dengan `APP_TIMEZONE`; attendance/close memakai IANA timezone lokasi/cabang dan work date shift lokal. Audit append-only menolak UPDATE, DELETE dan TRUNCATE. Ini bukan jaminan anti-tamper terhadap superuser/pemilik database; pemisahan role runtime/migrasi dan pengarsipan audit tetap kebutuhan deployment. Status modul di BACKEND_DELIVERY.md.

## Pengujian dan batas pengesahan

Tes backend memakai PostgreSQL khusus **`hris_testing`**, `DatabaseTransactions` dan rollback. Jangan arahkan tes ke database operasional. Buat database tersebut dengan operator PostgreSQL lalu migrasikan secara eksplisit memakai konfigurasi testing yang sesuai. Contoh PowerShell untuk migrasi database testing tanpa menulis kredensial:

```powershell
$previousDatabase = $env:DB_DATABASE
$previousUrl = $env:DB_URL
try {
    $env:DB_DATABASE = 'hris_testing'
    $env:DB_URL = ''
    php artisan migrate --env=testing
} finally {
    $env:DB_DATABASE = $previousDatabase
    $env:DB_URL = $previousUrl
}
php artisan test
```

Host, pengguna dan kata sandi harus menunjuk instance pengujian yang benar. Tes memeriksa nama database sebelum membuat fixture bisnis; database pengembangan tidak direset. Tes regresi `MigrationFreshTest` membuat database sementara bernama acak `hris_migration_test_*`, menjalankan `migrate:fresh --seed` dua kali hanya di sana, lalu menghapus database sementara itu. Pengguna PostgreSQL untuk tes ini memerlukan izin `CREATEDB`; tes domain lainnya memakai rollback pada `hris_testing`. Jangan menjalankan suite paralel terhadap satu database ini.

Fungsi PostgreSQL tetap ada setelah tabel dihapus oleh `migrate:fresh`, sehingga migrasi trigger memakai `CREATE OR REPLACE FUNCTION`. Jika sebelumnya migrasi gagal karena `Duplicate function`, jalankan `php artisan migrate --seed` setelah kode diperbarui untuk melanjutkan migrasi yang tertunda; tidak perlu menghapus database atau fungsi secara manual. Seeder tidak membuat akun default.

Tes mencakup password/MFA enrollment/challenge, kode pemulihan sekali pakai, rate limit, reset password, pemutusan sesi, akhir masa kerja, bootstrap/provisioning, scope self/team/branch/department/company, IDOR, riwayat penempatan, overlap/cycle serta audit immutable. Pengujian multi-proses dan beban belum dijalankan. HTTP/build tidak mengesahkan hasil visual: browser otomatis tidak tersedia dan verdict visual tetap **recapture** untuk login, challenge, MFA dan direktori desktop/ponsel.

Sebelum produksi: sediakan HTTPS, proxy tepercaya yang tepat, cookie aman, APP_DEBUG=false, mailer nyata, cache/session bersama yang persisten, backup/restore APP_KEY dan database, hak database minimum, pengawasan pengiriman reset serta UAT akses. Di production, request HTTP ditolak dan pengiriman reset ditolak jika mailer masih `log`/`array`. Respons permintaan reset sama untuk email dikenal/tidak dikenal. SMTP delivery belum diuji; mail lokal `log` bukan layanan pemulihan operasional.

Mutasi awal organisasi/karyawan dan persetujuan perubahan kerja kini tersedia pada [modul CoreHR](COREHR_WORKFLOW.md), dengan izin eksplisit company HR serta transaksi/audit. Workflow domain lain, payroll, impor dan demo persona menyusul. Fixture preview tetap terpisah dari data nyata.
