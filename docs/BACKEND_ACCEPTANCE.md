# Bukti backend dan penerimaan

Pemetaan acceptance PRD ke otomatisasi yang tersedia. Ini bukan pengesahan R0–R3 atau pernyataan setiap variasi kebijakan/volume/browser sudah diuji. Fixture keuangan sintetis; persetujuan pemilik payroll atas angka produksi tetap wajib.

| Acceptance | Bukti otomatis utama | Batas penerimaan |
| --- | --- | --- |
| AT-01/02/03 scope, revoke, no-self | CoreHrWorkflowTest, WorkflowOperationsTest, WorkflowExtensionsTest, SensitiveProfileTest, LifecycleAssetsTest, LeaveLotAccountingTest | UAT role/scope nyata dan review grants perusahaan |
| AT-04/10 transaksi/retry/reservation | WorkflowOperationsTest, LeaveDomainTest, ConcurrentLeaveTest, LeaveLotAccountingTest | Dua proses PHP diuji; bukan pengesahan throughput host produksi |
| AT-05/06/08/09 attendance | AttendanceDomainTest, AttendancePolicyTest, OvertimeCloseTest | GPS/photo browser, hardware dan policy nyata tetap UAT |
| AT-07 simulasi produksi | DemoIsolationTest, MigrationFreshTest | Demo deployment terpisah; operator mengelola host/secret/worker |
| AT-11/12 cuti kalender/reversal | LeaveDomainTest, LeaveGrantSchedulesTest, LeaveLotAccountingTest | HR mengesahkan hak/notice/calendar/staffing |
| AT-13 career as-of/history | CareerAdministrationTest, CoreHrWorkflowTest | Retrospective review adalah annotation + aplikasi prospektif; closed history tidak diedit |
| AT-14 kontrak historis | DocumentsTest | Status current dihitung tanggal; approval employment terpisah |
| AT-15 laporan historis/joiner | ReportsTest, PayrollTest | Payroll row adalah agregat batch, bukan departmental cost allocation |
| AT-16 roster/swap conflict | SwapCalendarTest, AttendancePolicyTest | Eligibility jabatan/lokasi eksplisit; custom certification registry tidak diasumsikan |
| AT-17 berkas privat | WorkflowOperationsTest, SensitiveProfileTest, DocumentsTest, PayrollTest | ACL storage/host dan retention ditentukan operator |
| AT-18 import/export | ImportTest, OpeningPayrollTest, ReportsTest | Limit 5000 rows/10MB; volume produksi/load belum disahkan |
| AT-19 payroll deterministik | PayrollTest, OpeningPayrollTest | Termasuk joiner/leaver/change dan YTD sintetis; bukan golden statutory payroll perusahaan |
| AT-20 klaim/kanal | ExpenseTest, PayrollTest | Template/rekonsiliasi bank nyata dan dispute memerlukan Finance |
| AT-21 recovery worker | WorkflowOperationsTest, WorkflowExtensionsTest, ImportTest, ReportsTest | Mail fake/sink; SMTP nyata, supervisor dan backoff host diverifikasi operator |
| AT-22 restore | BackupRestoreTest, DemoIsolationTest | Native pg_dump/pg_restore, dekripsi dan immutable audit diuji; production RPO/RTO belum diukur |

Nama test di atas menunjuk suite pada tests/Feature; beberapa skenario dibagi antar file. Gunakan hasil command test, bukan jumlah commit atau jumlah endpoint, untuk verifikasi build tertentu. [OPERATIONS.md](OPERATIONS.md) menjelaskan batas database disposable, worker timeout dan checklist penerimaan operator. [BACKEND_DELIVERY.md](BACKEND_DELIVERY.md) mencatat API/batas engine. [PROJECT_STATUS.md](PROJECT_STATUS.md) memisahkan frontend fixture dari integrasi operasional.
