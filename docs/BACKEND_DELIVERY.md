# Pelaksanaan backend penuh

Permintaan 21 Oktober 2026 mencakup seluruh backend PRD, bukan hanya CRUD. Commit/push berlangsung di dev per modul teruji. Tabel ini mencatat sisa pekerjaan dan tidak merupakan klaim acceptance.

| Urutan | Cakupan | Status |
| --- | --- | --- |
| 1 | Identitas/MFA, scope, organisasi, identitas kerja dan perubahan penempatan | Tersedia termasuk profil sensitif/enkripsi/verifikasi dan suspension API; integrasi layar belum lengkap |
| 2 | Approval bersama, idempotensi, outbox/inbox, reminder, berkas privat | API tersedia; delegation, kalender tenggat dan preferences belum lengkap |
| 3 | Kalender, shift/roster, clocking, geofence/foto, koreksi, swap | API tersedia; lihat batasan ATTENDANCE_LEAVE_BACKEND.md |
| 4 | Ledger cuti, pembatalan, lembur aktual, close/snapshot/adjustment | API tersedia; accrual/expiry otomatis dan acceptance masih perlu |
| 5 | Kontrak, surat/PDF, karier, lifecycle/rehire, aset/custody | API inti tersedia; lihat batasan DOCUMENTS_LIFECYCLE.md; acceptance penuh belum |
| 6 | Kompensasi/payroll, reimbursement, export/payment, slip privat | Belum tersedia; finalisasi memerlukan kebijakan disahkan |
| 7 | CSV/XLSX staging/import/export, laporan, pencarian berizin | Belum tersedia |
| 8 | Instalasi demo terisolasi, clock skenario, seed/persona/reset | Belum tersedia |
| 9 | Pengujian lintas domain/konkurensi, operasi, backup/restore/UAT | Belum disahkan |

Kredensial SMTP/infrastruktur, template bank, parameter pajak/BPJS, kebijakan hak cuti/lembur/prorata serta acceptance HR/Finance tidak akan dikarang. Mesin konfigurasi dan pengujian sintetis dapat dibangun tanpa menerapkan nilai produksi; finalisasi operasional harus menolak konfigurasi belum disahkan. `.env` tidak dilacak. Preview statis tetap terpisah sampai terhubung ke layanan nyata dan diuji.
