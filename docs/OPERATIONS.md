# Operasi backend HRIS

Paket executable/config tersedia pada [deploy/README.md](../deploy/README.md): template environment produksi, systemd worker/timer/FPM/nginx, installer Task Scheduler Windows, runner/verifier backup terenkripsi dan pemeriksaan heartbeat. `hris:check --production --services --json` menguji konfigurasi dan bukti liveness, bukan menyatakan email/backup/UAT sudah diterima. Tidak ada instalasi layanan atau deployment tersirat hanya karena file template tersedia.

Panduan operator instalasi. API domain tersedia; konfigurasi infrastruktur, kebijakan perusahaan dan UAT tetap keputusan manusia. Agent tidak mengirim email nyata, transfer bank atau deployment produksi.

## Pemeriksaan dan deploy

PHP CLI/web harus memakai extensions yang sama: pdo_pgsql, GD, ZIP, XML, mbstring dan OpenSSL sesuai composer.lock. PHP8.5 dipakai pada mesin pengujian. Instal dari lockfile memakai composer install, composer check-platform-reqs, composer validate --strict, npm ci dan npm run build. Jangan memakai composer update sebagai langkah deployment.

`php artisan hris:check` memeriksa driver, gambar/spreadsheet, adanya key tanpa mencetaknya, PostgreSQL, marker environment, migrasi dan trigger audit. `--production` menambahkan environment production, debug off, preview/demo off, HTTPS, cookie secure, queue asynchronous dan mail transport operasional. Exit1 berarti ada pemeriksaan gagal; command tidak mengirim email atau pembayaran. Tidak membuktikan SMTP/supervisor sudah berfungsi.

Cadangkan APP_KEY terpisah di secret store; jangan mengganti key tanpa rencana re-enkripsi. Runtime DB role dibatasi ke database aplikasi; migrasi memakai role DDL tersendiri. Superuser/pemilik database dapat mengubah trigger: archive audit dan akses DBA tetap dikendalikan operator. `.env`, key, passwords, private storage dan backup tidak boleh masuk Git/webroot.

Sebelum migrasi, verifikasi backup dapat direstore, aktifkan maintenance, hentikan scheduler dan biarkan worker **HRIS ini** selesai. Aplikasi Laragon/proyek lain tidak perlu dihentikan. Jalankan migrate --force, build assets, hris:check --production, hidupkan kembali worker/scheduler dan smoke-test login/MFA. Tidak memakai migrate:fresh/reset pada data operasional.

## Scheduler, queue dan pemulihan job

Jadwalkan Laravel setiapmenit melalui Task Scheduler/cron/service host. hris:dispatch-operations menangani reminder/delegasi/digest, expired export bytes, kontrak, revokasi exit, task deadlines, grant cuti dan expiry ledger. withoutOverlapping memakai cache instalasi. Pisahkan database/cache/session/queue/prefix dari proyek lain.

Worker persisten menjalankan CommitImport, BuildReportExport dan DeliverOutbox. Import timeout 300 detik: worker timeout harus lebih besar, dan retry_after lebih besar dari worker timeout agar job tidak diproses ganda. Contoh awal yang perlu diuji host: worker --timeout=330 dan DB_QUEUE_RETRY_AFTER=390. Default konfigurasi proyek kini 390 detik untuk database/Redis/Beanstalk; environment lama yang mengaturnya lebih pendek harus diperbarui. Production check menolak retry interval ≤300 detik. Restart worker sesudah deploy/config berubah.

Platform Admin membaca operations/health dan retry delivery beralasan, tanpa salary/medical payload. Pantau failed_jobs, delivery failed/lease/backoff, import failed dan report export expired/failed, memory, query latency serta disk privat. Perbaikan memakai retry/preview/adjustment domain, bukan SQL yang menimpa decision/ledger/final snapshot. SMTP at-least-once; Message-ID stabil tidak menjamin dedup provider.

## Backup/restore

Gunakan pg_dump custom format ke volume privat **terenkripsi**, role backup dan password dari pgpass/service/secret store, bukan argumen shell. Cadangkan database, private attachment storage, app/lockfile version dan APP_KEY terpisah. Dump database saja tidak mencakup foto/file filesystem. Hentikan mutasi/worker untuk pasangan database+storage konsisten, atau gunakan snapshot/WAL yang dikelola infrastruktur.

Restore memakai pg_restore --no-owner --no-privileges --exit-on-error ke **database baru terisolasi** yang telah diverifikasi belum ada; jangan menimpa hris. Jangan menjalankan HTTP/SMTP/integrasi publik dari hasil restore sebelum ACL/network/mail sink/environment diperiksa. Demo marker tidak boleh diubah menjadi production. Verifikasi record kontrol, migrations, immutable triggers, dekripsi sample, file hashes serta izin role/scope. Promotion recovery ke produksi keputusan operator setelah menilai RPO/RTO dan penerimaan HR/Finance.

BackupRestoreTest membuat database baru acak hris_backup_test_* dan hris_restore_test_*, memakai pg_dump/pg_restore asli, memeriksa data sintetis, dekripsi ciphertext dan audit trigger, lalu menghapus hanya keduanya. Tidak menyalin data perusahaan nyata. Binary PostgreSQL ada di PATH atau HRIS_PG_DUMP_BINARY/HRIS_PG_RESTORE_BINARY saat testing. Tes membuktikan mekanisme restore pada mesin pengujian; **RPO/RTO produksi belum diukur**, jadwal/off-host retention/restore drill harus ditetapkan perusahaan.

## Pengujian dan acceptance

Suite utama memakai hris_testing dan rollback. MigrationFreshTest, DemoIsolationTest, ConcurrentLeaveTest dan BackupRestoreTest memakai database disposable acak yang diperiksa sebelum reset/drop. Role test memerlukan CREATEDB; runtime produksi tidak memerlukannya. Jangan jalankan beberapa suite bersamaan pada hris_testing. Concurrency test menjalankan dua proses PHP dengan start barrier di database terpisah: hanya satu reservation atas saldo sama diterima dan replay tidak menggandakan ledger.

Payroll/Finance harus mengesahkan rule/source/effective dates, basis prorata dan layout bank; HR mengesahkan hak cuti, staffing dan exception policy. UAT lintas peran memakai data sintetis: onboarding→roster→clock/correction→close→payroll/slip; kontrak→renewal/exit; claim paid/failed→retry tanpa item lunas. SMTP staging diuji eksplisit operator. Form prototype tetap fixture, sehingga backend tests tidak mengesahkan form binding, browser/mobile, kamera/GPS permission atau accessibility UI.
