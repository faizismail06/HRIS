# Ruang kerja operasional

Masuk melalui /login, selesaikan MFA, lalu buka /app atau /app/modules. Navigasi tetap di atas, tanpa sidebar. /prototype/* tetap menjadi fixture visual terpisah; tindakan aplikasi berada pada /app/*.

## Cakupan integrasi

WorkspaceOperations memuat 124 formulir/tindakan dengan label Indonesia, field bertipe, pilihan catatan berizin, rincian hasil dan tautan kelanjutan. Pencarian pekerjaan menyaring tindakan sesuai mandat. Pengguna tidak memasukkan JSON atau URL API.

| Area | Alur operasional |
| --- | --- |
| Akun dan Core HR | MFA/recovery, organisasi/karyawan/perubahan efektif; profil umum/identitas/rekening/pajak dan usulan verifikasi; status/mandat/suspension akun |
| Pengajuan | Seluruh 20 jenis: draf, bukti privat, submit/withdraw/independent decision. Form cuti/lembur/koreksi/pembatalan tetap pada /app/requests; swap dan jenis khusus melalui Ruang kerja |
| Attendance dan cuti | Roster/replacement/publish, kalender tim/cabang, lokasi/template, GPS/foto/remote, actual overtime, close/reopen/snapshot; jenis/kredit/debit/carry-over/entitlement approval |
| Dokumen dan karier | Template, kontrak/renewal/amendemen/cancel, surat/reissue, preview tanpa nomor, PDF privat, karier prospektif/retrospektif |
| Lifecycle | Template checklist, onboarding/rehire/aktivasi, offboarding, tugas/dependency/bukti, pergantian owner dan closure clearance |
| Aset | Daftar/pendaftaran, serah-terima, konfirmasi sendiri, pengembalian/kehilangan, consumable, pembacaan bukti dan keputusan settlement Finance |
| Payroll | Kompensasi/opening YTD, draft policy/expert approval, reguler/off-cycle/corrections, hitung/review/approve/finalize/reset, rilis slip privat, bank template/confirm/export/outcome |
| Klaim | Kebijakan, draf/bukti/approval, alokasi kanal, settlement terpisah, reimbursement batch/approval/instruksi/outcome/retry sisa |
| Data | Delapan jenis impor CSV/XLSX: upload → map header nyata → preview/errors → commit checksum; template CSV. Delapan laporan dengan filter, export antrean, status dan private download |
| Administrasi | Preferences, delegasi/revoke/reassignment, bulk per item/versi, workflow/business deadlines, custom profile fields, staffing, health/retry |
| Audit dan demo | /app/audit: metadata untuk auditor company. /demo/operator: token, persona, waktu dan simulasi clocking sendiri pada instalasi demo tervalidasi |

## Izin dan konsistensi

Form memakai adapter controller domain yang sama dengan API. Endpoint/metode/workflow berasal dari katalog server. Session, CSRF, MFA, throttle, versi optimistis, idempotency dan aturan domain tetap diperiksa. Versi berasal dari pilihan yang ditinjau pengguna, tidak diganti diam-diam dengan versi terbaru saat POST.

Lookup mengembalikan label/referensi/versi dan metadata konfigurasi berizin. Ciphertext, alasan privat, nominal gaji dan kredensial tidak dikirim lewat lookup. Scope kasus/tugas/kontrak/pengajuan diperiksa. Pilihan rekan swap dibatasi cabang/departemen shift sendiri yang diterbitkan. Profil/kompensasi memakai pembaca privat dan audit; visibility ringkasan tidak otomatis membuka proposal. Bukti medis mengikuti pembatasan file medis.

Nominal diisi dalam rupiah tanpa pemisah ribuan; pecahan memakai titik. Server mengubah ke sen dengan integer. Timestamp memakai zona eksplisit; form tanpa timestamp tidak menampilkan zona. Nilai 0/false dipertahankan, grup opsional kosong dihilangkan, field tambahan tidak diteruskan.

Mandat auditor default-nya baca saja. Ekspor memerlukan auditor_export_enabled yang diberikan eksplisit melalui administrasi akun; perubahan security version mencabut akses file lama. Request/worker/status/download memeriksa ulang izin. Flag ini tidak membuka laporan finansial tanpa peran domain yang sesuai.

## Pengoperasian

Provisi akun awal tetap melalui terminal tepercaya hris:provision-account; kata sandi tidak dikirim ke browser operator atau argumen shell. Setelah tersedia, Platform Admin meninjau/mengubah mandat, izin ekspor dan suspension dari UI. Lihat [IDENTITY_FOUNDATION.md](IDENTITY_FOUNDATION.md).

Import background, report export, outbox, grant/expiry dan pekerjaan terjadwal memerlukan worker/scheduler. UI tidak menjalankan supervisor atau menandai bank/email berhasil. Lihat [OPERATIONS.md](OPERATIONS.md).

Kebijakan payroll/pajak/BPJS/prorata dan layout bank tidak diisi/disahkan agent. Draft policy belum memenuhi gate finalisasi. Kontrol waktu demo hanya memengaruhi layanan yang mendukung ScenarioClock, bukan jam OS atau kalender seluruh engine. Reset demo tetap command berpenjaga.

## Verifikasi

OperationWorkspaceTest memeriksa seluruh route/form/schema lookup dan alur aset/impor. OperationFinanceTest menjalankan kompensasi/rekening sampai payroll/slip privat dengan angka sintetis. OperationAdministrationTest memeriksa surat/template/preview/PDF, checklist/preferences/field, akun/audit serta grant/revoke ekspor auditor. OperationFieldsTest memeriksa presisi, tanggal, array tidak sah dan 0/false. DemoIsolationTest memeriksa operator UI dan tidak adanya token konfigurasi pada HTML.

OperationBrowserTest menjalankan JavaScript aplikasi melalui Chrome headless dan Node dengan profil terpisah serta respons pilihan sintetis. Tiga skenario memeriksa pergantian versi, tambah/hapus pemetaan, versi profil nol dan overflow pada viewport 390 px. Screenshot desktop/ponsel tersimpan lokal pada .cache/operation-browser. Ini bukan pengujian GPS/kamera fisik, SMTP nyata, semua varian prototype, atau UAT HR/Finance.

Hasil regresi dan commit dicatat pada [HANDOFF.md](HANDOFF.md). Host, kebijakan perusahaan, load/RPO/RTO produksi serta penerimaan operator tidak disimpulkan dari jumlah endpoint/commit.
