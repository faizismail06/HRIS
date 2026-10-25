# Inventaris visual statis

Cakupan layar implementasi, bukan penerimaan domain R0–R3. Semua route memerlukan preview local/testing. Form/kontrol statis tidak menyimpan atau mengirim data. Review visual browser untuk katalog statis secara penuh masih tertunda; ini terpisah dari pemeriksaan Chrome pada beberapa halaman operasional `/app/*`.

Pintu masuk: [Panduan layar](http://127.0.0.1:8000/prototype/screens).

## Beranda peran

- [Karyawan](http://127.0.0.1:8000/prototype/portals/employee)
- [Manager](http://127.0.0.1:8000/prototype/portals/manager)
- [HR](http://127.0.0.1:8000/prototype/portals/hr)
- [Finance](http://127.0.0.1:8000/prototype/portals/finance)
- [IT & Aset](http://127.0.0.1:8000/prototype/portals/it)
- [Auditor](http://127.0.0.1:8000/prototype/portals/auditor)
- [Admin platform](http://127.0.0.1:8000/prototype/portals/platform)

## Formulir

48 spesifikasi, masing-masing kondisi awal dan validasi gagal. Layout/field dapat ditinjau; aksi dinonaktifkan.

| Modul | Layar awal | Validasi gagal |
| --- | --- | --- |
| Core HR | [Permintaan perubahan profil saya](http://127.0.0.1:8000/prototype/screens/self-profile) | [Invalid](http://127.0.0.1:8000/prototype/screens/self-profile?state=invalid) |
| Lifecycle & Aset | [Tugas checklist dan bukti penyelesaian](http://127.0.0.1:8000/prototype/screens/checklist-task) | [Invalid](http://127.0.0.1:8000/prototype/screens/checklist-task?state=invalid) |
| Core HR | [Lokasi kerja, kalender dan radius](http://127.0.0.1:8000/prototype/screens/work-location) | [Invalid](http://127.0.0.1:8000/prototype/screens/work-location?state=invalid) |
| Administrasi | [Kebijakan cuti dan saldo awal](http://127.0.0.1:8000/prototype/screens/leave-policy) | [Invalid](http://127.0.0.1:8000/prototype/screens/leave-policy?state=invalid) |
| Employment | [Template dan penomoran surat](http://127.0.0.1:8000/prototype/screens/document-template) | [Invalid](http://127.0.0.1:8000/prototype/screens/document-template?state=invalid) |
| Keuangan | [Detail pembayaran dan verifikasi bank](http://127.0.0.1:8000/prototype/screens/bank-details) | [Invalid](http://127.0.0.1:8000/prototype/screens/bank-details?state=invalid) |
| Keuangan | [Rilis slip dan distribusi privat](http://127.0.0.1:8000/prototype/screens/payslip-release) | [Invalid](http://127.0.0.1:8000/prototype/screens/payslip-release?state=invalid) |
| Core HR | [Tambah / ubah karyawan](http://127.0.0.1:8000/prototype/screens/employee) | [Invalid](http://127.0.0.1:8000/prototype/screens/employee?state=invalid) |
| Core HR | [Unit organisasi dan cabang](http://127.0.0.1:8000/prototype/screens/organization) | [Invalid](http://127.0.0.1:8000/prototype/screens/organization?state=invalid) |
| Core HR | [Periode kerja / bergabung kembali](http://127.0.0.1:8000/prototype/screens/employment-period) | [Invalid](http://127.0.0.1:8000/prototype/screens/employment-period?state=invalid) |
| Kehadiran | [Susun dan publikasikan roster](http://127.0.0.1:8000/prototype/screens/roster) | [Invalid](http://127.0.0.1:8000/prototype/screens/roster?state=invalid) |
| Kehadiran | [Clock-in / clock-out](http://127.0.0.1:8000/prototype/screens/clock) | [Invalid](http://127.0.0.1:8000/prototype/screens/clock?state=invalid) |
| Pengajuan | [Koreksi absensi](http://127.0.0.1:8000/prototype/screens/correction) | [Invalid](http://127.0.0.1:8000/prototype/screens/correction?state=invalid) |
| Pengajuan | [Ajukan cuti / izin](http://127.0.0.1:8000/prototype/screens/leave) | [Invalid](http://127.0.0.1:8000/prototype/screens/leave?state=invalid) |
| Pengajuan | [Rencana lembur](http://127.0.0.1:8000/prototype/screens/overtime) | [Invalid](http://127.0.0.1:8000/prototype/screens/overtime?state=invalid) |
| Kehadiran | [Konfirmasi aktual lembur](http://127.0.0.1:8000/prototype/screens/overtime-actual) | [Invalid](http://127.0.0.1:8000/prototype/screens/overtime-actual?state=invalid) |
| Pengajuan | [Tukar shift](http://127.0.0.1:8000/prototype/screens/shift-swap) | [Invalid](http://127.0.0.1:8000/prototype/screens/shift-swap?state=invalid) |
| Pengajuan | [Tinjau dan putuskan pengajuan](http://127.0.0.1:8000/prototype/screens/approval) | [Invalid](http://127.0.0.1:8000/prototype/screens/approval?state=invalid) |
| Pengajuan | [Delegasi approver](http://127.0.0.1:8000/prototype/screens/delegation) | [Invalid](http://127.0.0.1:8000/prototype/screens/delegation?state=invalid) |
| Employment | [Susun / perpanjang kontrak](http://127.0.0.1:8000/prototype/screens/contract) | [Invalid](http://127.0.0.1:8000/prototype/screens/contract?state=invalid) |
| Employment | [Susun / terbitkan ulang surat](http://127.0.0.1:8000/prototype/screens/letter) | [Invalid](http://127.0.0.1:8000/prototype/screens/letter?state=invalid) |
| Employment | [Perubahan penugasan / karier](http://127.0.0.1:8000/prototype/screens/career) | [Invalid](http://127.0.0.1:8000/prototype/screens/career?state=invalid) |
| Lifecycle & Aset | [Buat checklist onboarding](http://127.0.0.1:8000/prototype/screens/onboarding) | [Invalid](http://127.0.0.1:8000/prototype/screens/onboarding?state=invalid) |
| Lifecycle & Aset | [Ajukan offboarding](http://127.0.0.1:8000/prototype/screens/offboarding) | [Invalid](http://127.0.0.1:8000/prototype/screens/offboarding?state=invalid) |
| Lifecycle & Aset | [Daftarkan aset / barang](http://127.0.0.1:8000/prototype/screens/asset) | [Invalid](http://127.0.0.1:8000/prototype/screens/asset?state=invalid) |
| Lifecycle & Aset | [Serah terima / pengembalian aset](http://127.0.0.1:8000/prototype/screens/asset-handover) | [Invalid](http://127.0.0.1:8000/prototype/screens/asset-handover?state=invalid) |
| Lifecycle & Aset | [Kasus aset rusak / belum kembali](http://127.0.0.1:8000/prototype/screens/asset-case) | [Invalid](http://127.0.0.1:8000/prototype/screens/asset-case?state=invalid) |
| Keuangan | [Komponen dan versi aturan payroll](http://127.0.0.1:8000/prototype/screens/payroll-components) | [Invalid](http://127.0.0.1:8000/prototype/screens/payroll-components?state=invalid) |
| Keuangan | [Siapkan batch payroll](http://127.0.0.1:8000/prototype/screens/payroll-run) | [Invalid](http://127.0.0.1:8000/prototype/screens/payroll-run?state=invalid) |
| Keuangan | [Tinjau dan finalisasi payroll](http://127.0.0.1:8000/prototype/screens/payroll-finalize) | [Invalid](http://127.0.0.1:8000/prototype/screens/payroll-finalize?state=invalid) |
| Keuangan | [Ekspor pembayaran bank](http://127.0.0.1:8000/prototype/screens/bank-export) | [Invalid](http://127.0.0.1:8000/prototype/screens/bank-export?state=invalid) |
| Keuangan | [Rekonsiliasi / retry pembayaran](http://127.0.0.1:8000/prototype/screens/reconciliation) | [Invalid](http://127.0.0.1:8000/prototype/screens/reconciliation?state=invalid) |
| Keuangan | [Ajukan reimbursement](http://127.0.0.1:8000/prototype/screens/reimbursement) | [Invalid](http://127.0.0.1:8000/prototype/screens/reimbursement?state=invalid) |
| Keuangan | [Settlement klaim](http://127.0.0.1:8000/prototype/screens/claim-settlement) | [Invalid](http://127.0.0.1:8000/prototype/screens/claim-settlement?state=invalid) |
| Kehadiran | [Tinjau dan tutup periode](http://127.0.0.1:8000/prototype/screens/close) | [Invalid](http://127.0.0.1:8000/prototype/screens/close?state=invalid) |
| Kehadiran | [Buka ulang sebelum payroll final](http://127.0.0.1:8000/prototype/screens/reopen) | [Invalid](http://127.0.0.1:8000/prototype/screens/reopen?state=invalid) |
| Keuangan | [Penyesuaian periode tertutup](http://127.0.0.1:8000/prototype/screens/adjustment) | [Invalid](http://127.0.0.1:8000/prototype/screens/adjustment?state=invalid) |
| Laporan | [Filter laporan dan ekspor berizin](http://127.0.0.1:8000/prototype/screens/report-export) | [Invalid](http://127.0.0.1:8000/prototype/screens/report-export?state=invalid) |
| Administrasi | [Akun, peran dan scope](http://127.0.0.1:8000/prototype/screens/account-access) | [Invalid](http://127.0.0.1:8000/prototype/screens/account-access?state=invalid) |
| Administrasi | [Workflow dan kebijakan efektif](http://127.0.0.1:8000/prototype/screens/workflow) | [Invalid](http://127.0.0.1:8000/prototype/screens/workflow?state=invalid) |
| Administrasi | [Unggah, petakan dan tinjau impor](http://127.0.0.1:8000/prototype/screens/import-upload) | [Invalid](http://127.0.0.1:8000/prototype/screens/import-upload?state=invalid) |
| Administrasi | [Preferensi notifikasi](http://127.0.0.1:8000/prototype/screens/notification-preferences) | [Invalid](http://127.0.0.1:8000/prototype/screens/notification-preferences?state=invalid) |
| Akun | [Masuk ke ruang kerja](http://127.0.0.1:8000/prototype/screens/login) | [Invalid](http://127.0.0.1:8000/prototype/screens/login?state=invalid) |
| Akun | [Verifikasi dua langkah](http://127.0.0.1:8000/prototype/screens/mfa) | [Invalid](http://127.0.0.1:8000/prototype/screens/mfa?state=invalid) |
| Akun | [Pulihkan akses akun](http://127.0.0.1:8000/prototype/screens/recovery) | [Invalid](http://127.0.0.1:8000/prototype/screens/recovery?state=invalid) |
| Akun | [Tetapkan kata sandi baru](http://127.0.0.1:8000/prototype/screens/reset-password) | [Invalid](http://127.0.0.1:8000/prototype/screens/reset-password?state=invalid) |
| Akun | [Aktivasi undangan akun](http://127.0.0.1:8000/prototype/screens/invite) | [Invalid](http://127.0.0.1:8000/prototype/screens/invite?state=invalid) |
| Demo | [Skenario demo terisolasi](http://127.0.0.1:8000/prototype/screens/demo) | [Invalid](http://127.0.0.1:8000/prototype/screens/demo?state=invalid) |

## State umum

| State | Pratinjau |
| --- | --- |
| not-found | [Catatan tidak ditemukan](http://127.0.0.1:8000/prototype/states/not-found) |
| confirmation | [Konfirmasi tindakan berbatas scope](http://127.0.0.1:8000/prototype/states/confirmation) |
| empty | [Belum ada tugas](http://127.0.0.1:8000/prototype/states/empty) |
| loading | [Contoh tampilan sedang memuat](http://127.0.0.1:8000/prototype/states/loading) |
| error | [Perubahan belum berhasil disimpan](http://127.0.0.1:8000/prototype/states/error) |
| forbidden | [Akses tidak tersedia](http://127.0.0.1:8000/prototype/states/forbidden) |
| stale | [Data berubah setelah Anda membukanya](http://127.0.0.1:8000/prototype/states/stale) |
| queued | [Permintaan contoh masuk antrean](http://127.0.0.1:8000/prototype/states/queued) |
| success | [Contoh pengajuan diterima](http://127.0.0.1:8000/prototype/states/success) |
| offline | [Koneksi belum tersedia](http://127.0.0.1:8000/prototype/states/offline) |
| permission-denied | [Izin kamera atau lokasi tidak diberikan](http://127.0.0.1:8000/prototype/states/permission-denied) |
| expired | [Sesi atau tautan sudah berakhir](http://127.0.0.1:8000/prototype/states/expired) |

## Pemetaan PRD

| Kelompok PRD | Visual yang tersedia |
| --- | --- |
| AUTH/akses | Login, MFA, recovery/reset/invite, akun/scope, matriks peran, forbidden/expired/not-found |
| CoreHR/organisasi | Direktori/profil/struktur/riwayat, tambah-ubah, profil sendiri, lokasi/kalender, periode/rehire |
| Approval/tugas | Progres pengajuan, keputusan, delegasi, versi workflow, empty/stale/konfirmasi |
| Roster/attendance | Jadwal/shift/event, susun-publikasi, clock, koreksi, izin perangkat ditolak |
| Leave/overtime/close | Pengajuan, ledger/kalender, kebijakan/saldo, rencana/aktual lembur, review/close/reopen/adjustment |
| Employment | Kontrak/arsip, template/nomor, surat/snapshot, karier sebelum-sesudah |
| Lifecycle/assets | On-offboarding, task owner/due/dependency/evidence, custody/return, kasus penyelesaian |
| Payroll/expense | Komponen, run/check/finalisasi, rilis slip, bank verification/export/reconciliation, klaim/settlement |
| Reports/import/audit | Laporan/sumber/arsip, filter/export/queue, mapping/validasi/duplikat impor, jejak audit |
| Notifications/search | Inbox/detail/preferensi, pengiriman gagal/antre, pencarian sepuluh jenis |
| Demo | Persona/lokasi/waktu skenario, seed/reset dengan konfirmasi scope, tanpa operasi nyata |

## Batas penyelesaian

Inventaris ini melengkapi cakupan visual statis inti. Daftar/detail lama ditautkan dari katalog. Tidak menyatakan alur bisnis, variasi runtime, otorisasi, database atau acceptance operasional selesai. Ketelitian layout, keyboard, zoom, kontras dan screen reader tetap perlu pemeriksaan browser. [Status proyek](PROJECT_STATUS.md) memisahkan implementasi statis dari pekerjaan tersebut.
