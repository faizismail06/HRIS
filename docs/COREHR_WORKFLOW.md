# Organisasi dan perubahan karyawan

Modul ini mengaktifkan master organisasi, pembuatan identitas kerja dan workflow perubahan dasar pada database nyata. Navigasi tetap horizontal Ruang Tim. Preview `/prototype/*` tetap terpisah. Ini belum penyelesaian seluruh CORE-01–06/R0.

## Mulai memakai

Jalankan `php artisan migrate` setelah memperbarui kode, lalu build frontend. Akun platform tidak otomatis mendapat akses HR. Dari terminal tepercaya, buat akun kerja:

```powershell
php artisan hris:provision-account hr_admin
php artisan hris:provision-account hr_officer
```

Provisioning meminta identitas dan password tersembunyi, tanpa akun default. Masing-masing akun menyelesaikan MFA di `/login`. Pengajuan oleh HR Admin membutuhkan HR Admin lain untuk persetujuan. Pengaju dan karyawan subjek perubahan tidak boleh mengambil keputusan pada pengajuan itu.

1. HR Admin company membuka `/app/organization`, menambahkan cabang, departemen dan jabatan. Buat departemen sebelum jabatan; pilih zona waktu cabang yang benar.
2. HR Officer/Admin company membuka `/app/employees/create`. Nomor unik, nama, email kerja opsional, periode kerja dan penempatan awal disimpan dalam satu transaksi. Akun login dibuat terpisah.
3. Dari profil, pilih **Ajukan perubahan**. Identitas kerja mencakup nama/email kerja; penempatan mencakup cabang, departemen, jabatan dan atasan. Isi alasan kerja.
4. Buka `/app/employee-changes`. HR Admin company lain melihat perbandingan, alasan, tanggal, lalu menyetujui atau menolak dengan catatan. Pengajuan belum mengubah karyawan sebelum disetujui.
5. Persetujuan penempatan membuat versi rentang baru. Hari ini tetap memakai penempatan yang berlaku, tanpa bergantung pada scheduler. Riwayat tampil untuk HR yang mempunyai izin mutasi company.

## Izin dan konsistensi

| Operasi | Izin saat ini |
| --- | --- |
| Buat/ubah/arsip cabang, departemen, jabatan | HR Admin company |
| Tambah karyawan dan ajukan perubahan | HR Officer/Admin company |
| Tinjau perubahan | HR Admin company selain pengaju dan subjek |
| Baca direktori | Scope pembaca pada modul identitas tetap berlaku |
| Mutasi oleh Employee, Manager, Auditor, peran teknis/keuangan atau HR berscope terbatas | Ditolak |

Pembatasan company untuk mutasi adalah keputusan konservatif tahap ini. Scope cabang/departemen tetap bisa membaca data berizin. Otorisasi diperiksa server dan diulang dengan akun segar di dalam transaksi. FormRequests memvalidasi field, action memakai whitelist, locks mengurutkan perubahan perusahaan/akun, dan audit disimpan bersama transaksi. Lock challenge identitas tetap terpisah agar urutan lock tidak terbalik.

Versi master, versi karyawan dasar dan versi pengajuan diperiksa sebelum mutasi. Formulir lama atau keputusan berulang menghasilkan 409 tanpa menimpa pembaruan orang lain. Buka kembali formulir untuk meninjau versi terbaru. Pengajuan yang kedaluwarsa akibat versi karyawan berubah harus ditolak dan dibuat ulang; tidak ada persetujuan paksa.

Master tidak dihapus permanen. Arsip ditolak jika masih dipakai penempatan sekarang/mendatang; departemen juga ditolak bila memiliki anak/jabatan aktif. Referensi historis tetap tersimpan. Referensi arsip yang tidak berubah boleh dipertahankan ketika mengoreksi master arsip, tetapi tidak dapat dipakai untuk mengaktifkan master/penempatan baru. Master diperiksa kembali saat approval sehingga arsip setelah pengajuan tidak lolos.

Periode dan penempatan memakai **[mulai, akhir)**, dengan akhir eksklusif. Penempatan awal mengikuti periode kerja. Perubahan penempatan harus setelah hari ini dan berada di dalam penempatan yang ada, setelah tanggal mulai rentangnya. Approval membagi rentang lama di tanggal efektif dan menyimpan rentang baru sampai batas akhir sebelumnya. Jika tanggal sudah tiba saat tinjauan, buat pengajuan baru. Perubahan retroaktif/hari yang sama belum didukung agar tidak melewati aturan close/adjustment yang belum tersedia.

Pengajuan menyimpan snapshot nilai sebelum perubahan dan payload usulan. Nama master/atasan pada perbandingan mengikuti register saat dibuka; ID snapshot tetap tersimpan. Keputusan mencatat before/after, actor, tanggal dan versi di audit, tanpa password atau field sensitif yang tidak didukung. Kegagalan validasi, siklus, mismatch jabatan/departemen, overlap atau master nonaktif membatalkan seluruh transaksi.

## Batas cakupan

Nama perusahaan masih ditetapkan saat bootstrap; UI pengubahan profil perusahaan belum tersedia. Perubahan master langsung berlaku, bukan riwayat hierarki master bertanggal efektif. Nomor karyawan tidak dapat diubah melalui form umum. Dokumen, NIK, bank, pajak/BPJS, kontak darurat, pendidikan dan custom fields belum menerima input operasional. Verifikasi sensitif, pengajuan Employee sendiri, rehire, perubahan periode/offboarding, delegasi approval, engine workflow umum, notifikasi dan impor menyusul. Tidak ada kalkulasi payroll.

Tes mencakup form dan akses langsung, whitelist, snapshot, atomic create/review, no-self-approval, versi lama, tanggal efektif, perubahan scope pada tanggal berlaku, arsip dan rollback siklus. Tes PostgreSQL memakai rollback pada `hris_testing`; fresh hanya dijalankan pada database sementara khusus. Tes multi-proses/beban belum dilakukan. Lookup master/atasan memakai select native; optimasi data berjumlah besar belum dikerjakan.

Review source menyelesaikan fallback rentang terbuka, asosiasi validasi input/select/textarea dan retensi keputusan. Verdict visual **recapture**: browser tidak tersedia, sehingga desktop/ponsel, keyboard, zoom dan pembaca layar belum disahkan. Build/tes HTTP tidak menggantikan pemeriksaan tersebut.
