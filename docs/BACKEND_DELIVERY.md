# Pelaksanaan backend penuh

Permintaan 21 Oktober 2026 mencakup seluruh backend PRD, bukan hanya CRUD. Commit/push berlangsung di dev per modul teruji. Tabel ini mencatat sisa pekerjaan dan tidak merupakan klaim acceptance.

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
| 9 | Pengujian lintas domain/konkurensi, operasi, backup/restore/UAT | Belum disahkan |

Kredensial SMTP/infrastruktur, template bank, parameter pajak/BPJS, kebijakan hak cuti/lembur/prorata serta acceptance HR/Finance tidak akan dikarang. Mesin konfigurasi dan pengujian sintetis dapat dibangun tanpa menerapkan nilai produksi; finalisasi operasional harus menolak konfigurasi belum disahkan. `.env` tidak dilacak. Preview statis tetap terpisah sampai terhubung ke layanan nyata dan diuji.
