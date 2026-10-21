# Status proyek

Pembaruan 21 Oktober 2026: modul backend domain/API utama tersedia dan diuji di PostgreSQL, dikembangkan pada dev dan di-push bertahap. Frontend **Ruang Tim** tanpa sidebar mempunyai cakupan visual statis inti; form prototype belum otomatis terhubung ke API.

## Cakupan sekarang

| Area | Backend | UI/infrastruktur |
| --- | --- | --- |
| Akun/organisasi/karyawan | Login/MFA/scope, suspension/revoke, effective assignments, private profiles/custom fields dan import | Form CoreHR awal operasional; seluruh preview belum |
| Approval/notifikasi | Typed workflows, independent decisions, idempotency/locks, delegasi, business deadlines, outbox/retry/digest/preferences/health | Task/inbox belum seluruhnya terhubung; SMTP nyata belum |
| Attendance/cuti/lembur | Roster/staffing/GPS/photo/remote/field, corrections/swaps, ledger/grants/expiry/carry-over, HR exceptions, actual OT dan close/revisions | Kamera/GPS/browser dan UAT perusahaan belum disahkan |
| Administrasi | PDF/templates/numbering/snapshots/preview, kontrak, karier/retrospective, onboarding/rehire/exit/tasks/assets | Pengesahan lintas peran dan seluruh form binding belum |
| Finance | Draft policy expert gate, exact arithmetic/YTD/openings, regular/off-cycle adjustments, slips, payment outcomes dan reimbursement batch/unpaid-only retry | Kebijakan/layout bank belum disahkan; tidak ada transfer bank nyata |
| Reports/import/search | Atomic CSV/XLSX staging/templates, private expiring exports, historical snapshots/filtering dan scoped search | Load/query plans dan integrasi UI perlu UAT |
| Demo/operasi | Isolated installation/database/storage/queues, personas/time/GPS, watermark/mail sink/reset guards, deployment check, concurrency/native restore tests | Hosting, supervisor/SMTP/off-host backup, production RPO/RTO dan UAT belum |

Rincian API/batas engine: [BACKEND_DELIVERY.md](BACKEND_DELIVERY.md). Deployment, worker, restore dan penerimaan operator: [OPERATIONS.md](OPERATIONS.md). Kebijakan payroll disiapkan draft sesuai keputusan pengguna; agent tidak mengesahkan tarif/regulasi atau kebijakan produksi.

## Tahap berikut

Hubungkan dashboard/form ke API memakai session/CSRF/MFA, role/scope, optimistic version dan idempotency key. Uji perjalanan lintas peran sampai hasil final serta loading/validation/conflict/retry, lalu QA browser/mobile/accessibility, UAT HR/Finance dan deployment terkontrol. Modul API tersedia tidak berarti form statis disabled sudah berfungsi.

Inventaris visual mencakup tujuh beranda peran, 48 form awal/invalid dan 12 state di [STATIC_SCREENS.md](STATIC_SCREENS.md). QA render tetap **recapture** karena browser sebelumnya tidak tersedia; build/HTTP tests tidak menggantikan hasil render/keyboard/zoom/kontras/screen reader.

## Batas pengesahan

Tidak ada klaim acceptance penuh R0–R3/kesiapan produksi. Pengujian sintetis bukan golden payroll yang disahkan pemilik payroll perusahaan. Engine hanya menerima konfigurasi yang didukung; aturan baru memerlukan implementasi/tes/pengesahan sebelum diaktifkan. Demo satu instalasi per skenario; ScenarioClock tidak mengubah waktu global dan saat ini dipakai attendance/documents. RPO/RTO produksi, SMTP nyata dan beban host perlu dibuktikan operator.

Riwayat milestone/checks di [HANDOFF.md](HANDOFF.md). .env tidak dilacak. Tidak ada perubahan main atau force-push.
