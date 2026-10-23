# Data contoh lokal

Jalankan setelah migrasi, tanpa reset database:

```powershell
php artisan db:seed
```

DatabaseSeeder menjalankan LocalTestingSeeder hanya pada APP_ENV=local non-demo. Pada testing otomatis, DatabaseSeeder tetap kosong; tes seeder memanggil kelas yang diperlukan secara eksplisit pada hris_testing. Direct seeders menolak production/staging/demo, termasuk local dengan demo flag aktif. Bootstrap manual tetap utuh dan hanya diperlukan bila memilih provisioning manual sebelum perusahaan pertama tersedia.

## Seeder per bagian

Setiap seeder dapat dijalankan dengan `php artisan db:seed --class=NamaSeeder`. Prasyaratnya dijalankan otomatis. Sebagai contoh, EmployeeSeeder menyediakan organisasi dan akun HR yang diperlukan; LeaveBalanceSeeder menyiapkan karyawan dan jenis cuti sebelum mengisi lot/ledger.

| Kelas | Data pada instalasi kosong |
| --- | --- |
| CompanySeeder | Satu perusahaan; mempertahankan nama perusahaan yang ada |
| UserSeeder | Delapan akun administratif/HR dengan mandat seperti pada README |
| BranchSeeder | Dua cabang contoh: Jakarta dan Bandung, Asia/Jakarta |
| DepartmentSeeder | Dua departemen contoh: Operasional dan HR |
| JobSeeder | Empat jabatan contoh: manajer/staf per departemen |
| EmployeeSeeder | Enam karyawan, periode kerja dan penempatan, termasuk dua atasan |
| StaffUserSeeder | Satu akun Manager dan dua Employee, ditautkan ke karyawan contoh |
| LeaveTypeSeeder | Tiga jenis contoh: tahunan, sakit dan izin pribadi |
| WorkLocationSeeder | Dua lokasi contoh dengan koordinat sintetis |
| ShiftTemplateSeeder | Dua template shift contoh 08.00–17.00, istirahat tidak dibayar 12.00–13.00 |
| RosterSeeder | Jadwal published 14 tanggal dari hari dijalankan, untuk enam karyawan; Sabtu/Minggu libur |
| LeaveBalanceSeeder | Kredit tahunan contoh 5760 menit per karyawan pada tahun berjalan, beserta ledger |
| WorkflowSeeder | Enam rute contoh: cuti, pengecualian cuti HR, pembatalan cuti, koreksi, lembur, tukar shift |
| LocalTestingSeeder | Menjalankan seluruh bagian di atas |

Kode bisnis memakai awalan LOCAL- dan nama memakai Contoh/testing. Data ini bukan fixture /prototype atau instalasi demo terisolasi; data contoh benar-benar tersimpan di database pengembangan dan dapat diproses lewat /app. Nilai cuti, notice dua hari untuk tahunan, jam kerja, geofence dan libur akhir pekan adalah skenario pengujian, bukan ketentuan hukum atau kebijakan perusahaan yang disahkan. Payroll/pajak/BPJS/format bank tidak diisi atau disahkan oleh seeder ini.

## Akun contoh karyawan

| Email | Peran / karyawan |
| --- | --- |
| manager.sample@hris.test | Manager team / LOCAL-MGR-001 |
| employee.sample@hris.test | Employee self / LOCAL-EMP-001 |
| employee2.sample@hris.test | Employee self / LOCAL-EMP-002 |

Password acak akun baru ditampilkan saat pertama kali seeder membuat akun. Simpan dari terminal saat itu. Akun lama tidak direset, ditautkan ulang, diaktifkan ulang atau diberi mandat tambahan. Seeder tidak membuat secret/kode pemulihan MFA; testing lokal mengikuti HRIS_LOCAL_SKIP_MFA yang dipilih pengguna.

## Mencoba pengajuan

1. HR Admin membuka /app/organization dan /app/employees; master dan enam karyawan contoh tersedia. /app/work/request-leave_exception menampilkan pilihan karyawan dan tiga jenis cuti.
2. Pilih Contoh - Cuti Tahunan dan satu hari kerja dalam jadwal 14 hari. Shift hari kerja memiliki 480 menit kerja bersih. Tanggal form mulai/terakhir termasuk. Lengkapi alasan pengajuan serta dasar pengecualian HR, simpan draf, lalu kirim dari rincian pengajuan.
3. Login sebagai hr.reviewer@hris.test untuk persetujuan independen. HR pengaju tidak dapat menyetujui pengajuannya sendiri. Jenis sakit memerlukan bukti medis sebelum submit; seeder tidak mengarang atau mengunggah bukti.
4. Untuk cuti biasa, gunakan employee.sample@hris.test melalui /app/requests. Pilih hari kerja setidaknya dua hari mendatang untuk jenis tahunan contoh. Saldo sendiri tampil di /app/leave-balances. Manager contoh dapat membaca tim operasionalnya sesuai penempatan.

Tidak ada clock-in/out atau pengajuan/approval transaksi contoh yang dibuat otomatis. Lokasi onsite memakai koordinat contoh dan geofence nyata tetap divalidasi; atur lokasi pengujian yang tepat bila ingin mencoba GPS perangkat. Tidak ada bypass geofence, email atau pembayaran nyata yang dijalankan seeder.

## Menjalankan ulang

Kode/email yang sudah ada dilewati; nilai operator, password, status arsip dan riwayat tidak ditimpa. Perubahan pada data contoh tetap dihormati. Kredit memakai source key karyawan/tahun sehingga tidak digandakan pada tahun yang sama. Roster melewati tanggal karyawan yang sudah mempunyai shift dalam status apa pun; pengulangan pada hari lain hanya melengkapi tanggal belum tersedia dalam jendela 14 hari yang baru. Staffing warnings perusahaan tidak diakui otomatis. WorkflowSeeder tidak menambahkan routing untuk kind yang sudah memiliki definisi, termasuk yang dinonaktifkan operator.

Prasyarat yang diarsipkan/dicabut atau data yang sudah diubah dapat menyebabkan validasi seeder lanjutan menolak operasi. Seeder tidak memaksa perbaikan atau aktivasi ulang. Setiap bagian memakai transaksi, sedangkan bagian sebelumnya yang sukses tetap tersimpan jika bagian berikutnya gagal; tidak ada reset atau penghapusan data.
