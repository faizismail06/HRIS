# Pelaksanaan backend penuh

Permintaan21Oktober2026 mencakup seluruh backend PRD, bukan hanya CRUD. Modul domain/API utama telah dibangun dan di-push bertahap ke dev. Tabel memisahkan implementasi backend dari konfigurasi/pengesahan produksi; bukan klaim setiap acceptance PRD atau integrasi UI sudah diterima.

| Urutan | Cakupan | Status |
| --- | --- | --- |
| 1 | Identitas/MFA, scope, organisasi, identitas kerja dan perubahan penempatan | Tersedia termasuk profil sensitif/enkripsi/verifikasi dan suspension API; integrasi layar belum lengkap |
| 2 | Approval bersama, idempotensi, outbox/inbox, reminder, berkas privat | API termasuk delegasi, kalender tenggat bisnis, quiet hours/digest tersedia; SMTP nyata dan uji konkurensi proses belum disahkan |
| 3 | Kalender, shift/roster, clocking, geofence/foto, koreksi, swap | API tersedia; lihat batasan ATTENDANCE_LEAVE_BACKEND.md |
| 4 | Ledger cuti, pembatalan, lembur aktual, close/snapshot/adjustment | API, grant annual/monthly dengan pengesahan HR, posting expiry/reconciliation, source-linked carry-over dan debit adjustment tersedia; lihat LEAVE_GRANT_SCHEDULES.md; acceptance operasional belum |
| 5 | Kontrak, surat/PDF, karier, lifecycle/rehire, aset/custody | API termasuk rekomendasi renewal, pembatalan kontrak terjadwal, approval karier, pergantian owner/closure clearance dan settlement Finance tersedia; lihat DOCUMENTS_LIFECYCLE.md |
| 6 | Kompensasi/payroll, reimbursement, export/payment, slip privat | Mesin draft, approval kebijakan, snapshot/slip, corrections next-period/off-cycle, variation/YTD dan batch reimbursement/retry terpisah tersedia. Kebijakan produksi, layout bank nyata dan UAT Finance belum disahkan; lihat PAYROLL_BACKEND.md dan EXPENSE_BACKEND.md |
| 7 | CSV/XLSX staging/import/export, laporan, pencarian berizin | API staging/validasi/commit termasuk opening payroll dengan verifikasi, template CSV, laporan/ekspor privat dan pencarian tersedia; lihat IMPORTS_BACKEND.md dan REPORTS_SEARCH_BACKEND.md; pengujian beban/UAT belum |
| 8 | Instalasi demo terisolasi, clock skenario, seed/persona/reset | Guard per instalasi/database/storage/queue, seed, operator-token personas, clock/GPS simulation, watermark/mail sink dan scoped reset tersedia; lihat DEMO_BACKEND.md. Hosting demo dan UI kalender virtual penuh belum |
| 9 | Pengujian lintas domain/konkurensi, operasi, backup/restore/UAT | Suite domain/API, dua proses reservasi, native dump/restore sintetis dan hris:check tersedia. OPERATIONS.md memuat deploy/worker/backup. SMTP nyata, RPO/RTO host produksi, load/UAT dan deployment belum disahkan |

Kredensial SMTP/infrastruktur, template bank, parameter pajak/BPJS, kebijakan hak cuti/lembur/prorata serta acceptance HR/Finance tidak akan dikarang. Mesin konfigurasi dan pengujian sintetis dapat dibangun tanpa menerapkan nilai produksi; finalisasi operasional harus menolak konfigurasi belum disahkan. `.env` tidak dilacak. Preview statis tetap terpisah sampai terhubung ke layanan nyata dan diuji.

Tambahan akhir: HR notice-exception cuti/cancellation atas target berizin, career retrospective HR→Finance tanpa overwrite history, document preview tanpa nomor, snapshot placement per hari untuk laporan finansial dan Finance-only reassignment tahap Finance. Batas engine terdokumentasi (contohnya batch sinkron500employee, import5000rows/10MB, calendar-year grant schedule, kalender simulasi attendance/documents); aturan di luar dukungan harus dimodelkan/diuji sebelum diaktifkan.

Tahap berikut adalah integrasi form/dashboard ke API dan UAT/deployment dengan konfigurasi perusahaan. Prototype disabled belum otomatis operasional. Payroll draft, email sink dan demo isolation tidak dianggap pengesahan/transaksi produksi.

Pemetaan bukti otomatis terhadap AT-01–22 beserta batas pengesahannya ada di [BACKEND_ACCEPTANCE.md](BACKEND_ACCEPTANCE.md).

Integrasi layar dimulai: `/app/requests` menghubungkan draf cuti/pembatalan/lembur/koreksi, bukti privat dan submit/withdraw/independent approval ke layanan domain. `/app/notifications` memakai inbox nyata dengan baca/arsip; `/app/search` memakai pencarian berizin dengan tautan karyawan/pengajuan/download dokumen. Layar modul lain masih mengikuti status terperinci di [PROJECT_STATUS.md](PROJECT_STATUS.md).

Integrasi kehadiran: `/app/attendance` memakai shift/proyeksi/pencatatan server dan GPS browser (remote tanpa koordinat), bukti foto privat dan status periode. `/app/schedules` membuat draf pola dan publikasi dengan recheck version/checksum/scope/staffing; company HR mengonfigurasi lokasi/versi template dari `/app/schedule-settings/*`. Tidak ada konfigurasi perusahaan yang otomatis diterapkan. UI replacement, period close, actual OT serta administrasi/finance/import/report masih dilanjutkan.
