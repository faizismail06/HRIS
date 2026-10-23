# Paket operasi HRIS

Pengguna menyatakan pada23Oktober2026 bahwa deployment tidak direncanakan. Paket ini **opsional untuk masa depan**; tidak dipasang sebagai bagian penutupan aplikasi lokal. Gunakan `composer run dev` untuk stack pengembangan, tanpa Task Scheduler/systemd tambahan.

Paket ini siap ditinjau/dijalankan pada target yang dipilih. Belum ada server/domain, kredensial SMTP, recovery recipient backup atau pengesahan kebijakan perusahaan yang diberikan; file ini tidak mengklaim target produksi sudah dipasang.

## Produksi Linux

1. Gunakan PHP 8.5 CLI/FPM beserta extension lockfile, PostgreSQL dan Node untuk build. Buat akun OS khusus `hris`, rilis pada `/srv/hris/current`, storage/cache yang dapat ditulis akun tersebut, dan runtime DB role tanpa hak DDL/CREATEDB. Migrasi dijalankan operator dengan role DDL terpisah.
2. Salin `production.env.example` ke `.env` **pada host tujuan**, bukan menimpa `.env` pengembangan. Isi domain, DB dan SMTP melalui secret store. Gunakan APP_KEY instalasi yang sudah ada; jangan mengganti key data terenkripsi. Untuk instalasi baru saja, siapkan key baru dan cadangkan terpisah. Template sengaja gagal preflight selama nilai masih kosong/placeholder. Untuk DB remote gunakan TLS verify-full dan CA yang sesuai, bukan menyalin sslmode local secara buta.
3. Instal dependency dari lockfile, build asset dan terapkan migrasi tanpa reset: `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`. Jalankan dalam maintenance dengan layanan HRIS dihentikan dan backup terverifikasi sebelum upgrade data yang sudah ada.
4. Adaptasi pool FPM dan nginx dari `linux/*.example`, isi domain/path sertifikat yang nyata, uji konfigurasi nginx/FPM sebelum reload. Web root harus `public/`, bukan root repo. Pengelolaan DNS/sertifikat memerlukan domain/host yang dikuasai operator.
5. Jalankan `bash deploy/linux/install-services.sh` untuk preflight. Setelah konfigurasi lolos, `sudo bash deploy/linux/install-services.sh --install` memasang hanya unit HRIS. Script menolak mengganti unit berbeda tanpa review. Worker memakai timeout330s dan stop grace350s; retry390s. Timer menjalankan scheduler setiap menit.
6. Setelah satu tick lengkap: `php artisan hris:check --production --services --json`. Heartbeat scheduler ditulis setelah dispatch berhasil; heartbeat worker baru ditulis oleh job yang diproses. Umur lebih dari180detik, timestamp masa depan atau heartbeat kosong dinyatakan tidak sehat. Hasil JSON hanya boolean pemeriksaan, tanpa secret.

Sesudah deploy rilis baru, restart `hris-worker.service`; jangan memakai kill-all PHP atau menghentikan aplikasi lain. Pemeriksaan SMTP hanya memvalidasi konfigurasi, bukan mengirim email. Uji pengiriman ke penerima staging yang disetujui tetap memerlukan kredensial/otorisasi penerima.

## Pengembangan Windows/Laragon

Installer memakai PHP CLI yang diberikan dan direktori proyek yang di-resolve. Nama dua task diturunkan dari path proyek + SID pengguna; aplikasi lain tidak disentuh. PowerShell wrapper meluncurkan child tersembunyi dan mengulang worker setelah recycle normal.

```powershell
# Preview, tidak memasang/menyalakan task:
./deploy/windows/Install-HrisTasks.ps1 -PhpPath (Get-Command php).Source
# Setelah target lokal dipilih:
./deploy/windows/Install-HrisTasks.ps1 -PhpPath (Get-Command php).Source -Install
php artisan hris:check --services
```

Task berjalan sebagai pengguna yang sedang login, mulai saat logon, dan tidak menyimpan password. Ini bukan server24/7 setelah logout atau bukti hard timeout Windows: PHP Windows tidak menyediakan mekanisme timeout Unix yang dipakai worker Linux. Untuk produksi gunakan host/service manager dengan batas eksekusi yang telah diuji. Jangan menganggap task preview berarti service sudah dipasang.

## Backup terenkripsi

Runner Python3.11+ memakai `pg_dump` dan `age`; age belum terpasang pada mesin pengembangan saat paket dibuat. Sediakan recipient **publik** age, PostgreSQL service/pgpass dengan akses baca backup, dan volume privat/off-host. Recovery identity age serta APP_KEY disimpan terpisah, bukan dalam repo atau argumen berisi nilai secret.

```text
python scripts/operations/backup.py --destination <private-backup-directory-outside-app> --recipient <age-public-recipient> --pg-service <service-name> --preflight
```

Backup yang konsisten membutuhkan maintenance dan worker/scheduler **HRIS saja** dihentikan serta seluruh mutasi selesai. Setelah itu jalankan argumen yang sama tanpa `--preflight`, tambahkan `--quiesced`. Jangan mematikan maintenance sebelum layanan dan pemeriksaan diaktifkan kembali. Pada trafik/volume besar, gunakan strategi snapshot/WAL konsisten yang dikelola infrastruktur.

Runner membaca konteks non-secret dari aplikasi, memeriksa batas database dan menolak destination di dalam aplikasi/private storage. Database custom dump dan tar storage langsung dipipe dalam mode biner ke age; tidak ada file dump/tar polos di disk. Hanya setelah kedua proses berhasil, direktori partial diubah menjadi backup selesai. Kegagalan tidak menghasilkan manifest sukses. APP_KEY tetap harus dicadangkan terpisah. Runner tidak menghapus backup lama atau mengklaim sinkronisasi off-host selesai.

```text
python scripts/operations/verify_backup.py <completed-backup-directory>
python scripts/operations/verify_backup.py <completed-backup-directory> --identity <off-host-recovery-identity-file>
```

Perintah pertama memverifikasi hash ciphertext. Perintah kedua memverifikasi dekripsi, keterbacaan dump dan tar privat tanpa mengekstrak file atau mengubah database. Keduanya tetap melaporkan restore_verified=false: penerimaan recovery memerlukan restore ke DB/storage baru terisolasi, verifikasi dekripsi data aplikasi/file/audit/izin dan pengukuran RPO/RTO. Prosedurnya ada pada `docs/OPERATIONS.md`; BackupRestoreTest membuktikan native restore data sintetis, bukan recovery host produksi. Unit Python menggunakan cipher palsu berlabel TEST-ONLY untuk menguji transport biner, bukan membuktikan keamanan age.

## Penerimaan bisnis dan perangkat

Jalankan suite pada hris_testing/disposable saja dan UAT sintetis lintas peran: onboarding→aktivasi→roster→clock/koreksi→close→payroll/slip; kontrak/renewal/exit/clearance; claim approval→paid/failed→retry tanpa item lunas. GPS/kamera diuji pada perangkat sasaran. Owner HR/Finance tetap mengesahkan hak/tarif/prorata/pajak/BPJS/layout bank dan hasil golden payroll. Keputusan sebelumnya tetap berlaku: kebijakan yang belum diberikan disiapkan sebagai draft, tidak disahkan agent.
