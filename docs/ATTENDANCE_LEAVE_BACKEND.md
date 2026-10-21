# API jadwal, kehadiran, cuti dan penutupan

Layanan nyata tersedia di `/app/api` dengan sesi, CSRF, MFA dan idempotency UUID. Semua waktu clock berasal server; employee berasal akun. Parameter employee_id, occurred_at dan simulation pada clock ditolak. Preview UI lama tetap sintetis; endpoint backend bukan bukti seluruh layar sudah terhubung.

## Jadwal dan kehadiran

HR company membuat work-locations dan versi shift-templates: zona IANA, radius/akurasi, foto wajib, jam lokal, offset hari akhir, istirahat terjadwal, toleransi serta jendela clock. Roster preview/draft menerima rows atau pattern eksplisit employee_ids/starts_on/ends_on/working_days(ISO1–7)/shift_template_id. Tidak ada asumsi Senin–Jumat. Publikasi memeriksa versi/checksum, scope, status kerja, penempatan dan overlap PostgreSQL. Manager hanya dapat mengatur team grant-nya; role baca lain tidak menambah scope tulis.

Roster membekukan waktu, timezone, template/policy/location snapshot. Clock-in/out memeriksa shift milik akun, jendela, akurasi dan jarak Haversine di server. Koordinat disimpan terenkripsi, bukan audit/log umum. Penolakan mencatat kode alasan tanpa membuat kehadiran. Device-reported GPS tidak menjamin lokasi fisik; foto bukan biometrik. Foto JPEG/PNG maksimal5MB divalidasi decode/dimensi dan disimpan privat; foto wajib harus diunggah ke shift dalam15menit sebelumnya. Pemilik/HR scope yang tepat dapat mengunduh; manager/auditor read grant tidak otomatis membuka foto.

Event mentah append-only dan satu event per arah/shift. Work date mengikuti awal shift lokal meskipun pulang esok hari. Projection menggunakan interval primer dikurangi irisan unpaid breaks; work minutes floor ke menit utuh, late/early ceil ke menit dan late di luar toleransi dihitung dari awal kerja yang diwajibkan. Dua flag tetap terpisah. DAY_OFF, SCHEDULED, IN_PROGRESS, INCOMPLETE, ABSENT, ON_LEAVE dan LEAVE_CONFLICT berbeda. Absence baru setelah grace expiry; bukti hadir saat cuti tidak dihapus.

Koreksi memakai workflow `correction`, owner shift, alasan, batas default7hari dan waktu yang sudah terjadi. Approval menjaga raw event, memeriksa versi sumber, lalu mengubah projection. Untuk periode tertutup dibuat adjustment terpisah. Definisikan alur yang sesuai sebelum submit; approver hilang memblokir, tidak memilih orang pertama.

Tukar shift `swap` wajib tahap counterparty dahulu, lalu manager/HR. Rekan harus memberi persetujuan sendiri. Finalisasi memeriksa ulang versi, future dates, penempatan/jabatan penerima, no-overlap dan ketiadaan attendance/cuti/lembur terkait. Roster lama disupersede, tidak dihapus. Untuk pertukaran lintas tanggal, roster libur penerima harus sudah eksplisit. Headcount pada slot yang dipertukarkan tetap sama. Tidak ada pembatalan final yang mengembalikan roster diam-diam; ajukan pertukaran korektif dengan referensi versi baru.

## Cuti dan lembur

HR Admin company membuat versi leave-types dan kredit menit yang disahkan untuk employee/type_code/entitlement_year, valid_from/valid_until eksklusif dan source_key unik. Tidak ada angka hak cuti legal bawaan. Draft `leave` mengambil kebijakan server berdasarkan ID/version; roster wajib lengkap termasuk off. Hari tanpa jadwal kerja tidak dipotong. Half-day menggunakan split yang dikonfigurasi pada shift. Cross-year dialokasikan ke lots periode hak masing-masing.

Submit mereservasi atomik per segmen/lots, menolak overlap/insufficient balance. Approve melepaskan reservation dan mencatat usage sekali tanpa debit ganda; reject/withdraw melepas reservasi. Ledger append-only, lots/allocations terpisah. `leave_cancel` membutuhkan approval terpisah dan reversal; kredit kedaluwarsa tidak menjadi saldo tersedia. Periode tertutup menyimpan adjustment, snapshot lama tetap. Team calendar hanya menampilkan scheduled/unavailable/day_off, tanpa jenis/alasan medis atau lampiran.

`overtime` menyimpan rencana/otorisasi terpisah dari actual. Owner mengisi actual yang sudah terjadi dan berada dalam batas; manager/HR selain subjek memverifikasi attendance atau HR mencatat pengecualian beralasan. Hanya verified actual minutes menjadi input, bukan planned hours atau tambahan kehadiran otomatis. Tarif/regulatory payroll belum diisi. Sumber attendance yang berubah harus diverifikasi ulang sebelum close.

## Penutupan dan operasi

attendance-periods bergerak open→review→closed dengan expected_version/checksum. Review memeriksa gap roster per hari/periode kerja, incomplete/in-progress/scheduled/conflict, pengajuan cuti/koreksi dan overtime belum terverifikasi. Close memvalidasi ulang sumber dan menyimpan immutable snapshot/revision/hash. Reopen perlu alasan dan dilarang setelah payroll_finalized. Adjustment tidak menimpa snapshot tertutup.

work-calendar-dates menyimpan versi tanggal perusahaan manual; bukan klaim feed hari libur mutakhir. Kalender tidak otomatis mengganti roster. Status teknis outbox/queue/blocked tersedia bagi Platform Admin melalui operations/health; retry delivery harus beralasan dan diaudit. Endpoint belum merupakan dashboard UI operasional lengkap.

Ekstensi terbaru: staffing minimum/warning, replacement roster dan mode field/remote tersedia di ATTENDANCE_POLICIES.md; delegasi/kalender tenggat/preferences di WORKFLOW_EXTENSIONS.md; grant annual/monthly di LEAVE_GRANT_SCHEDULES.md; laporan/ekspor di REPORTS_SEARCH_BACKEND.md. Sisa cuti mencakup posting expiry/carry-over/debit adjustment, eligibility khusus selain jabatan dan beban/konkurensi multi-proses. Status lintas modul terkini ada di BACKEND_DELIVERY.md. Tidak ada klaim acceptance R1 atau seluruh backend.
