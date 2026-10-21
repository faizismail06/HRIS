# Pelaksanaan backend dan integrasi

Backend domain/API utama telah dibangun pada PostgreSQL dan dihubungkan ke ruang kerja operasional. /app/modules menyediakan 124 formulir/tindakan tambahan di samping layar Core HR, jadwal, attendance, cuti dan approval. Seluruh 20 jenis workflow dapat dibuka dan diproses sesuai izin. [WORKSPACE_OPERATIONS.md](WORKSPACE_OPERATIONS.md) memetakan UI dan pemeriksaannya.

| Domain | Rujukan implementasi |
| --- | --- |
| Identitas/Core HR | Akun/MFA/recovery/scope, organisasi/karyawan/placement, private profiles, suspension dan izin ekspor auditor tambahan. [IDENTITY_FOUNDATION.md](IDENTITY_FOUNDATION.md), [COREHR_WORKFLOW.md](COREHR_WORKFLOW.md) |
| Approval/operasi | Independent approval, idempotensi/locks/outbox/inbox, berkas privat, delegasi, deadlines, preferences/digest/reminder/health/retry. [OPERATIONS.md](OPERATIONS.md) |
| Attendance/cuti | Kalender/roster/GPS/foto/remote/koreksi/swap, ledger, HR exceptions, actual overtime, close/reopen/snapshot/adjustment. [ATTENDANCE_LEAVE_BACKEND.md](ATTENDANCE_LEAVE_BACKEND.md), [LEAVE_GRANT_SCHEDULES.md](LEAVE_GRANT_SCHEDULES.md) |
| Administrasi | Kontrak/surat/PDF/preview/numbering, renewal/cancel, karier/retrospective, onboarding/rehire/exit/checklist/closure dan aset/custody/settlement. [DOCUMENTS_LIFECYCLE.md](DOCUMENTS_LIFECYCLE.md) |
| Finance | Draft policy/expert gate, exact money/YTD, reguler/off-cycle/corrections, private slips, bank template/export/outcome, reimbursement/unpaid-only retry. [PAYROLL_BACKEND.md](PAYROLL_BACKEND.md), [EXPENSE_BACKEND.md](EXPENSE_BACKEND.md) |
| Data | CSV/XLSX staging/map/preview/commit/templates, opening payroll terverifikasi, scoped historical reports/search/private exports. [IMPORTS_BACKEND.md](IMPORTS_BACKEND.md), [REPORTS_SEARCH_BACKEND.md](REPORTS_SEARCH_BACKEND.md) |
| Demo | Isolated installation/database/storage/queue, operator-token persona/time/GPS, watermark/mail sink dan reset berpenjaga. [DEMO_BACKEND.md](DEMO_BACKEND.md) |
| Pemeriksaan | Domain/API/UI/browser fixture, dua proses reservasi, native dump/restore sintetis dan hris:check. [BACKEND_ACCEPTANCE.md](BACKEND_ACCEPTANCE.md), [HANDOFF.md](HANDOFF.md) |

Migration 2026_10_21_370000 menambahkan auditor_export_enabled dengan default false. Platform Admin memberi izin ekspor secara eksplisit; perubahan security version membatalkan akses file lama. Hak baca auditor tidak otomatis memberi hak ekspor atau akses payroll.

Kebijakan perusahaan dan acceptance produksi tidak dibuat-buat. Payroll tetap draft sampai sumber/aturan disahkan, format bank memerlukan konfirmasi independen, dan demo tidak melakukan pembayaran operasional. SMTP, supervisor/scheduler, TLS/ACL, backup off-host, load/RPO/RTO, perangkat nyata serta UAT HR/Finance memerlukan host dan penanggung jawab perusahaan. [OPERATIONS.md](OPERATIONS.md) memberi langkah pelaksanaannya.

Batas engine tetap eksplisit: batch sinkron 500 karyawan, impor 5000 baris/10MB, laporan 62 hari/5000 hasil, jadwal entitlement dalam kalender tahun, serta jam simulasi hanya pada layanan ScenarioClock. Aturan di luar dukungan memerlukan pemodelan/tes sebelum diterapkan; angka sintetis bukan pengesahan pajak/BPJS atau golden payroll. Prototype tetap fixture terpisah. .env tidak dilacak dan database operasional tidak di-reset.
