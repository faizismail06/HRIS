# Integrated Operational HRIS

HRIS berdasarkan [PRD Indonesia](PRD_HRIS_ID.md) dan [PRD English](PRD_HRIS_EN.md). Backend domain/API mencakup identitas/MFA/scope, profil privat, organisasi/karier, workflow/delegasi, roster/attendance/cuti/lembur/close, dokumen/kontrak/lifecycle/aset, payroll/reimbursement, impor/ekspor/laporan/notifikasi/pencarian serta demo terisolasi. [Peta backend](docs/BACKEND_DELIVERY.md) mencatat implementasi dan batasnya; [panduan operasi](docs/OPERATIONS.md) memuat worker, deployment dan backup/restore. Kebijakan payroll produksi tetap draft sampai disahkan HR/Finance.

**Aplikasi operasional:** masuk melalui `/login`, selesaikan MFA, lalu buka [Ruang kerja](http://127.0.0.1:8000/app/modules). Sebanyak 124 formulir/tindakan tambahan dan seluruh 20 jenis pengajuan terhubung ke layanan domain dengan izin, versi dan idempotensi. Panduan: [WORKSPACE_OPERATIONS.md](docs/WORKSPACE_OPERATIONS.md). Browser headless telah memeriksa tiga fixture desktop/ponsel; UAT perusahaan dan konfigurasi produksi memerlukan penerimaan operator.

**Pratinjau visual terpisah:** [Panduan layar](http://127.0.0.1:8000/prototype/screens) memuat tujuh beranda peran, 48 formulir awal/validasi gagal dan 12 state. Form preview sengaja tidak menyimpan data. Lihat [status proyek](docs/PROJECT_STATUS.md), [inventaris layar](docs/STATIC_SCREENS.md) dan [panduan identitas](docs/IDENTITY_FOUNDATION.md).

## Login dan backend operasional

Setelah koneksi PostgreSQL disiapkan, jalankan `php artisan migrate`, lalu `php artisan hris:provision-account platform_admin --bootstrap --company="Nama Perusahaan"` dari terminal interaktif. Perintah meminta nama, email dan password tersembunyi; tidak ada akun/password default. Buka `/login` dan selesaikan pengaturan MFA. Admin platform tidak otomatis memperoleh akses data karyawan. Panduan akun HR, scope, pengujian PostgreSQL dan batas kesiapan produksi ada di [IDENTITY_FOUNDATION.md](docs/IDENTITY_FOUNDATION.md).

## Organisasi dan karyawan operasional

Buat akun HR dengan `php artisan hris:provision-account hr_admin` atau `hr_officer`, lalu masuk dan selesaikan MFA. HR Admin company mengelola `/app/organization`; HR Officer/Admin company membuat karyawan dan pengajuan dari direktori. Persetujuan memerlukan HR Admin lain yang bukan pengaju/subjek. Panduan alur, izin dan batas cakupan: [COREHR_WORKFLOW.md](docs/COREHR_WORKFLOW.md).

## Pengajuan, notifikasi dan pencarian operasional

Layar operasional setelah login/MFA: `/app/requests` untuk cuti, pembatalan cuti, lembur, koreksi kehadiran dan persetujuan independen; `/app/notifications` untuk inbox/baca/arsip; `/app/search` untuk pencarian berizin. Pengajuan disimpan sebagai draf, bukti diunggah terpisah, lalu dikirim. Konfigurasi jenis cuti, roster, hak cuti serta definisi/reviewer workflow harus tersedia terlebih dahulu. Modul finansial/administrasi lain masih memerlukan integrasi UI; `/prototype/*` tetap fixture sintetis.

`/app/attendance` menghubungkan jadwal, detail waktu efektif/asli, foto privat dan clock-in/out. Lokasi diminta browser setelah tindakan pengguna; HTTPS/localhost diperlukan. `/app/schedules` menghubungkan pola roster → draf → pemeriksaan staffing → publikasi untuk HR company/manager team. HR company dapat menambahkan lokasi dan versi template di `/app/schedule-settings/locations` dan `/app/schedule-settings/templates`; angka kebijakan diisi HR, tidak disediakan sebagai kebijakan bawaan. GPS/kamera perangkat nyata dan QA browser masih perlu UAT. Jalankan `node --test tests/JavaScript/attendance.test.mjs` untuk tes retry/permission/error client.

`/app/overtime` menghubungkan pengisian aktual oleh karyawan dan verifikasi atasan/HR; menit bayar mengikuti aktual terverifikasi setelah istirahat dikurangi. Pengecualian tanpa dukungan attendance hanya untuk HR company beralasan. `/app/attendance-periods` tersedia bagi HR Admin company untuk membuat periode (tanggal terakhir termasuk), meninjau penghambat, menutup snapshot dan membuka kembali sebelum payroll final. Revisi snapshot lama dipertahankan dan sumber diperiksa ulang saat penutupan. Kedua layar dapat dibuka dari navigasi pemeriksaan pada halaman kehadiran.

Pengganti roster dapat dibuat dari rincian kehadiran melalui **Buat pengganti jadwal ini**; jadwal lama tetap berlaku sampai publikasi draf pengganti. `/app/team-calendar` menampilkan ketersediaan berizin tanpa alasan/bukti cuti; `/app/work-calendar` untuk company HR menetapkan versi tanggal kerja/libur, tanpa otomatis mengubah roster.

Menu Pengajuan memuat `/app/leave-balances` untuk saldo sendiri dan `/app/leave-management` untuk HR Admin company: versi jenis cuti, kredit manual berreferensi, pengurangan yang melindungi reservasi/pemakaian, carry-over dari lot sumber serta jadwal annual/monthly dengan approval HR Admin lain yang bukan penerima. Tanggal akhir di form termasuk; API tetap menggunakan batas exclusive-end. Jadwal approved diproses melalui scheduler `hris:dispatch-operations` yang harus disiapkan operator sesuai [panduan operasi](docs/OPERATIONS.md); tombol approval tidak langsung menerbitkan kredit. Tidak ada angka/kebijakan hak yang otomatis diisi atau disahkan oleh agent.

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

Buka [dashboard lokal](http://127.0.0.1:8000/prototype/dashboard). Halaman utama mengarah ke dashboard hanya saat preview aktif. Navigasi atas memuat Ringkasan, Karyawan, Organisasi, Kehadiran, Pengajuan, Administrasi dan Keuangan; Tugas dan Agenda adalah bagian lokal dashboard. Menu berpindah ke baris sendiri pada lebar 1280 px ke bawah dan dapat membungkus, tanpa sidebar. Pencarian/filter dan detail tetap tersedia. Semua beroperasi pada fixture baca saja; tidak ada keputusan persetujuan atau penyimpanan data HR. Tanggal snapshot tetap 20 Oktober 2026.

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

## Modul Payroll & Reimbursement

- [Batch payroll](http://127.0.0.1:8000/prototype/payroll): September final dengan pembayaran sebagian/gagal, Oktober dihitung ulang V2 dengan penghambat, serta rencana penyesuaian terpisah. Detail menampilkan sumber komponen, status versi, pemeriksaan, aktor persiapan/persetujuan dan penerima snapshot periode.
- Slip contoh: pratinjau HTML hanya untuk penerima pada batch final yang sudah dirilis. Komponen menyimpan snapshot nominal periode, bukan master bulan berikutnya. Status final perhitungan berbeda dari lunasnya pembayaran; bukan slip resmi/PDF.
- [Reimbursement](http://127.0.0.1:8000/prototype/reimbursements): enam klaim, kanal tunggal payroll atau batch terpisah, settlement berhasil/gagal dengan referensi/tanggal/aktor, pembayaran sebagian, bukti serupa yang perlu ditinjau dan status ditolak.

Nominal memakai integer IDR sintetis. Klaim Arif tetap Rp 420.000 meskipun baru Rp 200.000 berhasil; transfer gagal Rp 220.000 tidak menambah terbayar. Klaim Nadia hanya masuk payroll Oktober. Peringatan bukti serupa bukan keputusan kecurangan. Tidak ada tarif pajak/BPJS, formula/prorata regulasi, rekening, ekspor bank, transfer, PDF privat, limit klaim versi atau otorisasi slip produksi. Semua preview tetap GET-only; belum memenuhi acceptance payroll/expense R3.

## Modul Laporan & Penutupan Kehadiran

- [Laporan mingguan](http://127.0.0.1:8000/prototype/attendance-reports): filter minggu/cabang/departemen/karyawan, jumlah per hasil shift dan sumber yang dapat ditelusuri. Detail shift mempertahankan filter laporan saat kembali. Terlambat/pulang awal adalah subset Hadir selesai; shift berjalan dan percobaan ditolak tetap dibedakan.
- [Penutupan periode](http://127.0.0.1:8000/prototype/attendance-periods): contoh Terbuka, Dalam tinjauan dan Ditutup dengan versi sumber/tinjauan, roster/kebijakan, penghambat versus peringatan dan riwayat revisi. Arsip 28–30 September menyimpan snapshot tiga karyawan termasuk Intan dan tautan payroll final terkait.

Laporan berjalan mengambil fixture attendance; arsip tertutup terpisah dan bukan laporan bulan penuh. Hash SHA-256 hanya atas data contoh, bukan lock/transaksi produksi. Koreksi sesudah payroll final memerlukan penyesuaian terpisah; lembur rencana tidak otomatis menjadi masukan terbayar. Tidak ada penutupan/pembukaan ulang, ekspor, validator lengkap atau mesin pembayaran operasional. Browser/aksesibilitas belum diverifikasi.

## Modul Pengaturan, Audit & Impor

- [Pengaturan](http://127.0.0.1:8000/prototype/settings): rancangan scope sembilan peran, batas mandat, larangan self-approval, delegasi dan keamanan akun. Nilai target belum diterapkan; tidak membaca atau menampilkan isi `.env`.
- [Audit contoh](http://127.0.0.1:8000/prototype/audit): enam peristiwa sintetis, filter aktor/referensi/modul/hasil, konteks sebelum/sesudah, alasan dan korelasi. Ini bukan logger aktif atau audit aktivitas pengguna.
- [Pratinjau impor](http://127.0.0.1:8000/prototype/imports): empat batch dengan pemetaan, kolom/baris bermasalah dan perbaikan. Batch invalid menolak seluruh baris dalam skenario; payload berulang mengacu hash dan batch asal tanpa penggandaan contoh.

Tidak ada perubahan peran, unggahan, parsing CSV/XLSX, commit data atau ekspor. Formula pada contoh hanya teks. Batas 5.000 baris/10 MB adalah rancangan PRD, bukan limit yang sudah diberlakukan. Status selesai/deduplikasi pada batch adalah fixture; penulisan nyata oleh UI selalu nol. Modul belum memenuhi acceptance authorization/audit/import R0.

## Modul Notifikasi & Pencarian

- [Notifikasi](http://127.0.0.1:8000/prototype/notifications): enam pesan sintetis, status baca terpisah dari pengiriman email, detail pesan dan tautan sumber, antrean jam tenang serta retry contoh. Membuka detail tidak mengubah status baca. Kegagalan pengiriman tidak membatalkan pengajuan sumber.
- [Pencarian lintas modul](http://127.0.0.1:8000/prototype/search): minimal dua karakter, filter sepuluh kategori, hasil menuju route sumber. Indeks contoh memakai ringkasan terpilih tanpa nominal gaji, komponen slip, isi surat, alasan pribadi, email atau bukti klaim.

Dua tautan utilitas tersedia pada semua halaman preview, tanpa menambah tujuan global atau sidebar. Badge tiga pesan belum dibaca berasal dari fixture tetap. Tidak ada inbox/outbox tersimpan, pengiriman email, penandaan dibaca, preferensi aktif, retry job atau indeks/otorisasi pencarian produksi. Kembali dari hasil pencarian memakai browser Back; query tidak diteruskan sebagai filter lokal yang berbeda makna.

## Penyelesaian visual statis

- [Panduan layar](http://127.0.0.1:8000/prototype/screens) menautkan beranda Karyawan, Manager, HR, Finance, IT/Aset, Auditor dan Platform. Beranda memakai fixture bersama; pemilihan persona hanya tampilan, bukan pergantian akun atau izin.
- 48 formulir mencakup CoreHR, lokasi/kalender, pengajuan/persetujuan, roster/clock, employment, lifecycle/aset, keuangan, laporan, konfigurasi, akun dan demo. Masing-masing memiliki kondisi awal serta validasi gagal dengan nilai contoh lain dipertahankan. Semua field dan aksi transaksi dinonaktifkan; tidak menerima file atau kredensial.
- 12 state: kosong, memuat statis, gagal, terlarang, data berubah, antre, konfirmasi diterima, offline, izin perangkat ditolak, sesi/tautan berakhir, tidak ditemukan dan konfirmasi reset berbatas scope. Auth memiliki shell ringkas tanpa navigasi HR. Katalog juga memiliki tiga rute pemeriksaan lintas modul, bukan tes E2E bisnis.

Penggunaan nyata, perilaku input/submit, izin server dan semua proses domain tetap belum tersedia. Verifikasi visual desktop/mobile masih memerlukan browser; percobaan membuka IAB gagal karena browser tidak tersedia.

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
