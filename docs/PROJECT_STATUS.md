# Status proyek

Pembaruan 21 Oktober 2026: modul backend domain/API utama tersedia dan diuji di PostgreSQL, dikembangkan pada dev dan di-push bertahap. Frontend **Ruang Tim** tanpa sidebar mempunyai cakupan visual statis inti; form prototype belum otomatis terhubung ke API.

## Cakupan sekarang

| Area | Backend | UI/infrastruktur |
| --- | --- | --- |
| Akun/organisasi/karyawan | Login/MFA/scope, suspension/revoke, effective assignments, private profiles/custom fields dan import | Form CoreHR awal operasional; seluruh preview belum |
| Approval/notifikasi | Typed workflows, independent decisions, idempotency/locks, delegasi, business deadlines, outbox/retry/digest/preferences/health | /app/requests: daftar/filter/detail; draf cuti, pembatalan, lembur, koreksi; bukti privat; submit/withdraw/review operasional. /app/notifications memakai data inbox, baca/arsip. Jenis lain baru status; SMTP nyata belum |
| Attendance/cuti/lembur | Roster/staffing/GPS/photo/remote/field, corrections/swaps, ledger/grants/expiry/carry-over, HR exceptions, actual OT dan close/revisions | /app/attendance: jadwal/detail, foto privat, clocking; /app/schedules: pola/review/publish dan company master forms; /app/overtime: aktual/verifikasi/stale source/HR exception; /app/attendance-periods: review/blocker/close/reopen dan snapshot revisions. UI replacement/kalender/administrasi hak cuti serta QA perangkat/UAT belum |
| Administrasi | PDF/templates/numbering/snapshots/preview, kontrak, karier/retrospective, onboarding/rehire/exit/tasks/assets | Pengesahan lintas peran dan seluruh form binding belum |
| Finance | Draft policy expert gate, exact arithmetic/YTD/openings, regular/off-cycle adjustments, slips, payment outcomes dan reimbursement batch/unpaid-only retry | Kebijakan/layout bank belum disahkan; tidak ada transfer bank nyata |
| Reports/import/search | Atomic CSV/XLSX staging/templates, private expiring exports, historical snapshots/filtering dan scoped search | /app/search operasional: employee/request links dan private document download; aset baru ringkasan. UI report/import dan load/query plans perlu dilanjutkan |
| Demo/operasi | Isolated installation/database/storage/queues, personas/time/GPS, watermark/mail sink/reset guards, deployment check, concurrency/native restore tests | Hosting, supervisor/SMTP/off-host backup, production RPO/RTO dan UAT belum |

Rincian API/batas engine: [BACKEND_DELIVERY.md](BACKEND_DELIVERY.md). Deployment, worker, restore dan penerimaan operator: [OPERATIONS.md](OPERATIONS.md). Kebijakan payroll disiapkan draft sesuai keputusan pengguna; agent tidak mengesahkan tarif/regulasi atau kebijakan produksi.

## Tahap berikut

Lanjutkan UI replacement/kalender/hak cuti, administrasi, finance dan import/report. Form /app/requests dan attendance/roster/close/overtime menggunakan layanan domain yang sama dengan API, session/CSRF/MFA, optimistic version dan idempotency key. WorkflowWorkspaceTest menguji cuti→bukti→submit→independent approval, replay, konflik versi, izin dan privasi medis; waktu lembur memakai zona shift terpilih. AttendanceWorkspaceTest menguji clocking/pola/publikasi/konfigurasi lintas izin; CloseOvertimeWorkspaceTest menguji close/reopen/revision/payroll lock/actual verification/changed sources; node:test menguji retry/permission/session di client. Modul API tersedia tidak berarti seluruh form statis disabled sudah berfungsi.

Inventaris visual mencakup tujuh beranda peran, 48 form awal/invalid dan 12 state di [STATIC_SCREENS.md](STATIC_SCREENS.md). QA render tetap **recapture** karena browser sebelumnya tidak tersedia; build/HTTP tests tidak menggantikan hasil render/keyboard/zoom/kontras/screen reader.

## Batas pengesahan

Tidak ada klaim acceptance penuh R0–R3/kesiapan produksi. Pengujian sintetis bukan golden payroll yang disahkan pemilik payroll perusahaan. Engine hanya menerima konfigurasi yang didukung; aturan baru memerlukan implementasi/tes/pengesahan sebelum diaktifkan. Demo satu instalasi per skenario; ScenarioClock tidak mengubah waktu global dan saat ini dipakai attendance/documents. RPO/RTO produksi, SMTP nyata dan beban host perlu dibuktikan operator.

Riwayat milestone/checks di [HANDOFF.md](HANDOFF.md). .env tidak dilacak. Tidak ada perubahan main atau force-push.
