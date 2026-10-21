# Status proyek

Pembaruan 21 Oktober 2026: domain/API utama dan ruang kerja operasional lintas modul tersedia pada dev. Integrasi tambahan mencakup 124 formulir/tindakan dan seluruh 20 jenis workflow. Aplikasi memakai /app/*; /prototype/* tetap fixture visual lokal terpisah.

| Area | Implementasi |
| --- | --- |
| Identitas/Core HR | Login/MFA/recovery, scope/security version, organisasi/karyawan/placement, profil privat/custom fields, status/mandat/suspension dan izin ekspor auditor eksplisit |
| Workflow | Draf/bukti/submit/withdraw/decision, no-self approval, delegasi/reassignment/revoke, bulk per item/versi, kalender tenggat, inbox/preferences/digest/health/retry |
| Attendance/cuti | Kalender/roster/replacement/publish, GPS/foto/remote, koreksi/swap/HR exception, lembur aktual, close/reopen/snapshot; ledger/kredit/debit/carry-over/entitlement approval |
| Administrasi | Kontrak/renewal/amendemen/cancel, surat/PDF/preview, karier/retrospective, onboarding/rehire/aktivasi, exit/checklist/clearance, aset/custody/consumable/settlement |
| Finance | Draft policy expert gate, kompensasi/YTD, reguler/off-cycle/corrections, review/approve/finalize/slip, bank template/export/outcome dan klaim/reimbursement batch/unpaid-only retry |
| Data/audit | Delapan jenis staging import/templates, delapan laporan/ekspor privat, scoped search dan audit metadata berizin |
| Demo/operasi | Guard instalasi/database/storage/queue, seed/persona/operator UI/waktu/GPS, watermark/mail sink, scoped reset, installation check, concurrency dan native backup/restore sintetis |

Panduan alur/izin: [WORKSPACE_OPERATIONS.md](WORKSPACE_OPERATIONS.md). Batas backend: [BACKEND_DELIVERY.md](BACKEND_DELIVERY.md). Hasil tes dan delivery: [HANDOFF.md](HANDOFF.md).

## Penerimaan operator

Hal berikut membutuhkan nilai atau lingkungan perusahaan: pengesahan payroll/cuti/lembur/staffing/layout bank; SMTP/worker/scheduler/HTTPS/ACL dan backup off-host; UAT lintas peran/perangkat GPS-kamera/golden payroll; pengukuran load/RPO/RTO pada host tujuan. Agent tidak mengarang kebijakan, mengirim pembayaran, atau menerbitkan deployment.

Browser headless telah memeriksa tiga fixture operasional desktop dan viewport 390 px. Ini bukan recapture seluruh 48 form/12 state prototype atau acceptance aksesibilitas/perangkat seluruh layar. Batas volume/engine/jam skenario tetap berlaku sesuai dokumentasi domain. Kesiapan produksi tidak dinyatakan hanya karena tes sintetis lulus.

.env tidak dilacak. Commit/push bertahap ke dev; tidak ada force-push atau perubahan main.
