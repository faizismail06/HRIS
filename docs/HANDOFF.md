# Handoff inisialisasi — 20 Oktober 2026

## 21 Oktober — fondasi approval dan operasi

Mulai pelaksanaan seluruh backend sesuai [BACKEND_DELIVERY.md](BACKEND_DELIVERY.md). Fondasi mesin approval bersama tersedia sebagai layanan dan API sesi/CSRF/MFA; domain tanpa handler nyata tetap ditolak. Ada snapshot kandidat/tahap, idempotency receipts, efek keputusan atomik, blocked/reassign, inbox/outbox/delivery lease, scheduler/reminder dan berkas privat. Payload workflow dienkripsi/tidak diserialisasi. Suspension terpisah diperiksa runtime. Rincian dan batas: [WORKFLOW_OPERATIONS.md](WORKFLOW_OPERATIONS.md). Tes penuh121tes/1858assertions lulus. Domain attendance dan modul setelahnya masih dikerjakan; ini bukan penyelesaian seluruh backend.

## Organisasi dan workflow karyawan

Master cabang/departemen/jabatan kini menerima create/update/archive oleh HR Admin company. Nomor karyawan, nama/email kerja, periode dan penempatan awal dibuat atomik oleh HR Officer/Admin company. Perubahan nama/email dan cabang/departemen/jabatan/atasan melalui pengajuan dengan snapshot sebelum/usulan, catatan keputusan dan versi. Approval hanya HR Admin company lain; pengaju dan subjek ditolak. Scope terbatas tetap baca saja. Tidak ada hard delete, mutasi profil sensitif, rehire, lifecycle atau workflow umum.

Perubahan penempatan hanya mendatang dan membagi rentang [from,to) tanpa mengubah hari ini atau menunggu scheduler. Versi stale/review berulang menghasilkan409. Master aktif, jabatan/departemen, manager, overlap dan siklus diperiksa sebelum simpan; approval memvalidasi ulang master dan versi. Kegagalan membatalkan seluruh transaksi. Locks perusahaan/actor seragam dengan layanan akses; middleware challenge identitas tidak lagi mengambil lock actor sebelum layanan CoreHR mengambil lock perusahaan. Audit before/after memakai whitelist.

Pemeriksaan akhir: **114 tes / 1785 assertions lulus**, Pint, kompilasi Blade dan build Vite lulus. Tes validasi mengikuti cookie sesi pada redirect agar menguji flash input/error seperti browser. Migrasi tambahan berjalan di database pengembangan/testing tanpa reset pengembangan; fresh regression hanya pada database sementara. Tidak ada tes beban/multi-proses atau pengiriman nyata.

Review source menyelesaikan tiga temuan: fallback seterusnya, asosiasi error field input/select/textarea dan retensi keputusan. Komponen core-field mempertahankan kontrol native. Detector sekali hanya34advisory baseline lama. Verdict visual recapture karena browser kosong, tanpa pengesahan screenshot/keyboard/kontras. Documenter mempertahankan DESIGN.md/sidecar dan melaporkan deskripsi scope statis lama tanpa memperbaikinya di luar scope. Ruang Tim/no-sidebar dipertahankan. Panduan: [COREHR_WORKFLOW.md](COREHR_WORKFLOW.md), kontrak UI: [corehr-surface.md](corehr-surface.md). Bagian berikut adalah riwayat tahap sebelumnya.

## Perbaikan migrasi ulang PostgreSQL

`migrate:fresh --seed` sempat gagal karena fungsi PostgreSQL tetap ada setelah tabel dihapus. Keempat fungsi audit/riwayat kini memakai `CREATE OR REPLACE FUNCTION`, sehingga definisi dapat dipasang ulang tanpa duplicate function. Database pengembangan yang gagal dipulihkan dengan `migrate --seed` tanpa reset tambahan. Regresi memakai database sementara bernama acak, menjalankan fresh+seed dua kali serta memverifikasi trigger audit masih menolak update; database sementara dibersihkan setelah tes. Gabungan tes migrasi dan identitas: 19 tes / 180 assertions lulus. Pengguna PostgreSQL untuk tes migrasi ini memerlukan CREATEDB; database pengembangan tidak digunakan untuk reset regresi.

## Backend pertama: identitas, MFA dan skema CoreHR

Login nyata melalui Fortify tersedia di `/login`, pengaturan MFA di `/account/security`, beranda `/app` dan direktori baca saja `/app/employees`. MFA wajib sebelum membaca data; scope query membatasi self/team/branch/department/company dan detail lintas scope menghasilkan 404. Platform Admin tidak memperoleh akses HR otomatis. Pendaftaran publik dan mutasi direktori ditutup. Preview tetap terpisah, sintetis dan baca saja.

Migrasi PostgreSQL telah dijalankan pada database pengembangan yang sebelumnya kosong serta database khusus `hris_testing`. Tabel organisasi, periode kerja/penempatan bertanggal efektif, grants dan audit tersedia. Exclusion constraints menolak overlap, trigger menolak siklus departemen/atasan serta penempatan di luar periode, dan audit menolak update/delete/truncate. Tabel jabatan memakai `job_positions` agar tidak berbenturan dengan antrean Laravel `jobs`. Akun operasional nyata belum dibuat; `DatabaseSeeder` tidak membuat akun default. Bootstrap/provision dan revoke disediakan lewat terminal tepercaya.

Tes penuh pertama: **90 tes / 1519 assertions lulus**, termasuk 78 tes preview lama dan 12 tes backend. Setelah tambahan pengujian provisioning/bootstrap, akhir masa kerja, pembatasan MFA, kebijakan default deny, manual setup dan validasi password, suite backend terbaru: **18 tes / 168 assertions lulus**. Tes PostgreSQL memakai rollback transaksi pada `hris_testing`; tidak ada `migrate:fresh` atau penghapusan data pengembangan. Pint, kompilasi Blade dan build Vite lulus. Uji beban/multi-proses, SMTP dan UAT belum dilakukan.

Review source menemukan kebutuhan setup MFA manual pada satu ponsel; disclosure kunci TOTP beserta penerbit/akun telah ditambahkan dan dinilai resolved. Verdict visual tetap **recapture** karena browser inventory kosong: capture desktop/ponsel login, challenge, setup/confirmed dan direktori belum tersedia. Detector sukses sekali, hanya advisory baseline lama; documenter mempertahankan DESIGN.md/sidecar karena ekstensi mengikuti Ruang Tim. Tidak ada klaim kelulusan render atau aksesibilitas browser.

Panduan operasional, grants, keamanan, pengujian dan batas scope: [IDENTITY_FOUNDATION.md](IDENTITY_FOUNDATION.md). Kontrak UI: [identity-surface.md](identity-surface.md). Tahap berikutnya CRUD organisasi/karyawan melalui policy, validasi dan transaksi di atas fondasi ini. Ini belum acceptance R0 atau kesiapan produksi. Bagian berikut adalah riwayat modul terdahulu.

## Penyelesaian cakupan visual statis

Pengguna meminta seluruh visual statis diselesaikan. Ditambahkan katalog `/prototype/screens`, 48 spesifikasi `/prototype/screens/{screen}` dengan state ready/invalid, 12 halaman `/prototype/states/{state}` dan tujuh beranda `/prototype/portals/{persona}`. Katalog menghubungkan daftar/detail lama, role views, formulir dan tiga rute peninjauan lintas modul. `docs/STATIC_SCREENS.md` memetakan kelompok PRD. Status proyek kini membedakan cakupan implementasi statis yang telah dilengkapi dari QA browser tertunda dan backend yang belum tersedia.

Beranda Employee menggunakan Nadia: libur20Okt, shift berikut21Okt, saldo7hari/reservasi2hari, profil/jabatan kini dan pengajuan sendiri. Manager Bima menampilkan tiga laporan langsung bekerja, task aktif OVT-007 dan exception shift tim. Finance memisahkan transfer gagal dan sebagian; IT menampilkan readiness Maya/clearance Intan. HR, Auditor, Platform mempunyai mandat/tujuan masing-masing. Header menerima optionalpersona/navigation untuk variasi ini; default tujuh tujuan tidak berubah. Persona hanya tampilan, tidak mengubah akun atau izin.

48 form mencakup seluruh kelompok inti termasuk profiledit/lokasi/kebijakan, delegatedapproval, aktuallembur, templatesurat, tugaschecklist, bankverification/rilisslip, close/reopen/adjustment, auth/MFA/recovery dan demo. Fieldset disabled, tidak ada nama field submit; password kosong/file tidak diterima. Dua kondisi menggunakan GETtervalidasi. Tombol dinonaktifkan dengan label spesifik, auth tidak menyediakan draf. Dua belas state meliputi akses/expiry tanpa payload, input contoh setelah gagal, V1/V2 stale, antre, konfirmasi scope reset dan izin perangkat ditolak. Tidak ada requestGPS/kamera, email, upload, reset, login, transaksi atau mutasi apa pun.

Pint, build Vite, kompilasi Blade, HTTP smoke checks awal/ulang dan 78 tes/1403 assertions lulus. Seluruh 48 form pada dua kondisi, tujuh portal dan 12 state dirender melalui tes. Review source menemukan konteks input offline dan tipe field numerik; keduanya diperbaiki dan dinilai resolved. Format retained values dipindahkan ke controller setelah tes menangkap masalah directive Blade; semua tes final lulus. Browser IAB dicoba langsung: `Browser is not available: iab`. Tidak ada screenshot atau kelulusan visual/aksesibilitas. Detector sekali hanya advisory baseline enam warna, 23 ukuran font dan lima radius; token di luar scope tidak diperbaiki. Kontrak: `docs/static-completion-surface.md`. Bagian berikut riwayat modul sebelumnya.

## Modul terbaru: Notifikasi & Pencarian Lintas Modul

Preview GET-only `/prototype/notifications`, `/prototype/notifications/{notification}`, `/prototype/search` memakai `PrototypeWorkspace`. Globalnav tetap tujuh; dua tautan utilitas ada di main di atas previewnotice dan dapat membungkus. Badge tiga belum dibaca mempunyai nama aksesibel pada tautan, angka visual aria-hidden. Tidak ada sidebar. `docs/PROJECT_STATUS.md` kini mencatat modul tersedia sebagai UI, bukan inbox/search operasional; fase proyek tetap prototipe UI.

Enam notifikasi: tiga unread, dua read, satu archived. Status emailNONE/SENT/FAILED/QUEUED terpisah dari status baca dan sumber. Detail/backscope mempertahankan q/state/delivery. COR-026 gagal email setelah tiga percobaan contoh sebelum jamtenang, jadwal berikut09.00WIB, pengajuan tetapIN_REVIEW. CTR-001 queued tanpa percobaan, tidak memperpanjang kontrak. Jamtenang21–09/digest09 adalah contoh, tidak disimpan. Pesan tidak memuat nominal gaji, alasan medis atau lampiran. GET tidak menandai dibaca atau mengubah transaksi.

Pencarian minimal2maks100karakter, filter10kategori, initial/empty/reset. Proyeksi eksplisit dari fixture existing, tanpa privatepayload, email, nominal, isi surat atau bukti klaim. Route/parameter tetap dari kode; query tidak memilih endpoint. Hasil memakai browser Back untuk konteks pencarian, tidak mengoper q ke filter sumber berbeda makna. Belum ada indeks berizin, otorisasi owner/team atau querylogging produksi.

Pint, build, kompilasi Blade, enam HTTP smoke checks serta HTTP ulang setelah perbaikan copy dan 71 tes/921 assertions lulus. Review statis tidak menemukan cacat logika material; satu temuan copy istilah implementasi sudah diganti bahasa pengguna dan dinilai resolved. Verdict visual tetap `recapture` karena browser inventory kosong; visual/aksesibilitas belum disahkan. Detector hanya baseline enam warna, 23 ukuran font dan lima radius. Tidak ada pengiriman, retryjob/outbox, preferensi/mutasi atau koneksi ke data operasional. Kontrak UI `docs/workspace-surface.md`. Bagian berikut adalah riwayat modul sebelumnya.

## Status dan modul terbaru: Pengaturan, Audit & Impor

Pengguna bertanya apakah semuanya selesai dan tahap sekarang. Jawaban: belum; ini masih tahap prototipe UI dengan fixture baca saja, belum autentikasi/policies, database bisnis, workflow atau implementasi operasional R0–R3. `docs/PROJECT_STATUS.md` kini menjadi peta cakupan dan sisa pekerjaan, ditautkan dekat awal README. Tahap pengerjaan fondasi/UI/backend/integrasi pada dokumen itu berbeda dari release R0–R3 PRD. UI berikutnya masih notifikasi/pencarian lintas modul, pengalaman Employee/Manager dan formulir/state transaksi; visual browser juga belum disahkan.

Preview GET-only `/prototype/settings`, `/prototype/audit`, `/prototype/imports`, `/prototype/imports/{batch}` menggunakan `PrototypeAdministration`. Globalnav tujuh tetap, local Administrasi kini delapan tautan yang membungkus tanpa sidebar. Pengaturan menampilkan target sembilan peran, mandat dan batas; tidak membaca `.env` atau menerapkan konfigurasi. Audit enam fixture berlabel bukan log nyata, dengan before/after/alasan/korelasi tanpa payload sensitif.

Empat batch impor: IMP-001 memiliki tiga baris, dua bermasalah (tiga pesan kolom), nol diterapkan; IMP-002 roster lintas tengah malam siap ditinjau tetapi tidak dipublikasikan; IMP-003 contoh selesai dua baris saldo, tanpa perubahan nyata; IMP-004 payload/jenis/hash sama dengan IMP-003, nol diterapkan dalam skenario dan tautan ke asal. Formula '=2+3' hanya teks, tidak dieksekusi. Tidak ada parser/unggahan/commit atomik/deduplikasi operasional, logger append-only atau manajemen izin sebenarnya. Semua query whitelist dan detail/backscope dipertahankan.

Pint, build, kompilasi Blade, enam HTTP smoke checks dan 63 tes/695 assertions lulus. Review statis tidak menemukan cacat material yang terkonfirmasi. Verdict visual `recapture`: browser inventory kosong dan screenshot belum dapat dibuat; visual/aksesibilitas belum diverifikasi. Detector hanya advisory baseline enam warna, 23 ukuran font dan lima radius. Kontrak UI di `docs/administration-surface.md`. Bagian berikut adalah riwayat modul sebelumnya.

## Modul terbaru: Laporan & Penutupan Kehadiran

Preview GET-only `/prototype/attendance-reports`, `/prototype/attendance-periods` dan `/prototype/attendance-periods/{period}` memakai `PrototypeReports`. Localnav Kehadiran menjadi empat tujuan Rekap/Jadwal/Laporan/Penutupan; header global tetap tujuh. Laporan mingguan mengambil fixture bersama `PrototypeAttendance`, filter minggu/cabang/departemen/karyawan divalidasi. Tautan sumber menuju detail shift memakai from=reports dan mempertahankan report_week, cabang, departemen serta karyawan saat kembali. URL navigasi sebelumnya tetap dipertahankan.

Hadir selesai=PRESENT dengan dua clock fixture; terlambat/pulang awal subset, bukan kelompok tambahan. IN_PROGRESS terpisah walau memiliki clock-in; rejected attempt tidak dihitung hadir. Total baris hari/roster termasuk libur, bukan total hari kerja. Tiga periode: OPEN12–25Okt; REVIEW12–18Okt dengan V2 tidak sama tinjauanV1 serta dua incomplete; CLOSED cuplikan28–30Sep tiga profil termasukIntan. Arsip memakai snapshot hardcoded, referensi sumber teks, hashSHA256 fixture dan payrollfinalSeptember; tidak menghitung profil sekarang atau mengklaim rawSeptember tersedia. Roster Oktober konsisten RST-OCT-V1 dengan detail shift.

Tidak ada mutasi close/reopen, DB lock, checksum concurrency, validator lengkap, ekspor atau engine lembur. Rencana OVT-007 belum punya aktual terverifikasi. Aturan koreksi setelah payrollfinal diterangkan sebagai penyesuaian terpisah, bukan overwrite. Pint, build, kompilasi Blade, enam HTTP smoke checks dan 56 tes/614 assertions lulus. Review statis tidak menemukan cacat material; temuan kecil tautan sumber periode dua minggu sudah diperbaiki dengan dua tautan berlabel dan dinilai resolved. Verdict visual tetap `recapture` karena browser tidak tersedia; visual/aksesibilitas belum diverifikasi. Detector hanya baseline enam warna, 23 ukuran font dan lima radius. Kontrak UI ada di `docs/reports-surface.md`; bagian berikut riwayat modul terdahulu.

## Modul terbaru: Payroll & Reimbursement

Preview GET-only `/prototype/payroll`, `/prototype/payroll/{batch}`, `/prototype/payroll/{batch}/payslips/{employee}`, `/prototype/reimbursements`, `/prototype/reimbursements/{claim}` memakai `PrototypeFinance`. Header global kini tujuh tujuan dengan Keuangan; navigasi menempati baris sendiri pada <=1280 px, tetap membungkus tanpa sidebar. Filter batch q/status/periode dan klaim q/status/karyawan/cabang di-whitelist; konteks kembali dipertahankan, lintas payroll/klaim tidak membawa filter berbeda makna.

Tiga batch dan enam klaim, semua nominal integer IDR sintetis. September final total17.500.000 tetapi terbayar berhasil7.400.000, termasuk transfer Arif gagal dan pembayaran Intan sebagian. Snapshot September tidak mengikuti nilai nominal Oktober. Oktober V2 kehilangan tinjauan/persetujuan V1 dan memiliki penghambat kehadiran belum ditutup, parameter belum disahkan dan nilai negatif. Komponen kerja tetap dapat dilihat sebelum final; route slip ditolak sebelum final/rilis atau bagi penerima di luar batch. Rencana koreksi Intan terpisah, bukan perubahan batch final dan bukan potongan aset.

EXP-001 hanya kanal payrollOktober. EXP-002 nominal asal420.000, berhasil200.000, gagal220.000 dan sisa220.000. EXP-003 lunas dengan referensi/tanggal/aktor; EXP-004/005 peringatan bukti serupa tanpa menetapkan kecurangan; EXP-006 Finance-verified belum approved-for-payment. Bukti/transfer hanya kode contoh, tanpa berkas atau rekening. Tidak ada tarif hukum, ekspor, bankAPI, PDF, mesin payroll atau transaksi pembayaran operasional.

Pint, build, kompilasi Blade, tujuh HTTP smoke checks serta 49 tes/541 assertions lulus. Review statis tidak menemukan cacat material. Verdict visual `recapture`: browser inventory kosong, sehingga screenshot/visual/aksesibilitas belum dapat diverifikasi. Detector tetap advisory baseline enam warna, 23 ukuran font dan lima radius. Lihat `docs/finance-surface.md` untuk kontrak UI dan batas scope. Bagian berikut merupakan riwayat modul sebelumnya.

## Modul terbaru: Onboarding, Offboarding & Aset

Preview `/prototype/lifecycle`, `/prototype/assets` dan detail masing-masing memakai `PrototypeLifecycle` serta GET-only controller. Empat kasus dan enam inventaris/barang sintetis; Administrasi kini lima tautan lokal, header global tetap enam tujuan tanpa sidebar. Filter scope kembali dipertahankan, lintas register hanya cabang/karyawan. Cabang siklus kerja dari profil saat ini; cabang aset dari lokasi inventaris.

Maya belum memiliki akun/perangkat aktif karena dependensi verifikasi; Raka tetap offboarding sampai 31 Oktober dengan contoh cabut akses 1 November 00.00 WIB. Intan telah berakhir dan akses aplikasi dicabut dalam skenario, sementara laptop lewat tenggat tetap open. Fajar memiliki periode onboarding baru 2024 dan clearance terdahulu terpisah. Reservasi perangkat tidak membuat pemegang aktif; setiap fixture maksimal satu custody aktif. Barang habis pakai bukan pinjaman. Kasus aset rusak/belum kembali tidak memotong gaji otomatis. Bukti dan petugas IT berupa teks sintetis, bukan integrasi/unggahan.

Pint, build, kompilasi Blade, enam HTTP smoke checks dan 41 tes/424 assertions lulus. Review statis tidak menemukan cacat material. Verdict visual `recapture`: browser inventory kosong, sehingga visual/aksesibilitas dan screenshot masih memerlukan verifikasi. Detector hanya advisory baseline enam warna, 23 ukuran font dan lima radius. Tidak ada mutasi akun, pencabutan sesi, pengecualian HR, scheduler, pemrosesan bukti atau penyelesaian finansial operasional. Kontrak UI: `docs/lifecycle-surface.md`. Bagian berikut adalah riwayat modul.

## Modul terbaru: Kontrak, Surat & Perubahan Karier

Preview GET-only tersedia pada `/prototype/contracts`, `/prototype/letters`, `/prototype/career-changes` dan detail `/{record}` masing-masing. `PrototypeEmployment` menyediakan enam kontrak, empat surat dan tiga perubahan karier sintetis. Filter q/status/cabang/karyawan divalidasi per jenis; detail mempertahankan scope kembali, sedangkan lintas jenis hanya membawa cabang/karyawan. Header kini enam tujuan dengan Administrasi sebagai pintu tiga register. Pada lebar 1100 px ke bawah nav menempati baris sendiri dan dapat membungkus, tetap tanpa sidebar.

Kontrak terjadwal Nadia mulai 1 November tidak mengganti versi aktif atau jabatan Koordinator pada snapshot 20 Oktober. Arsip 2025 tidak menimpa 2026. Pengingat 60/30/14/7 hari pada kontrak aktif berupa tanggal contoh; tidak mengirim notifikasi atau memperpanjang/menutup hubungan kerja. Surat memakai snapshot terpisah dari profil: ejaan lama LTR-001 tetap “Operasi”, LTR-004 terbit ulang “Operasional” dengan nomor baru dan referensi dua arah. Surat Intan memakai periode kerja selesai; draf Maya belum diterbitkan/tidak bernomor final. Perubahan karier membedakan usulan, terjadwal dan efektif; profil saat ini tetap konsisten.

Build Vite, Pint, kompilasi Blade, lima HTTP smoke checks dan 34 tes dengan 322 assertions lulus. Review statis tidak menemukan cacat kode material. Verdict visual Impeccable: `recapture`, karena browser tidak tersedia (inventory kosong) dan screenshot desktop/mobile belum dapat dibuat; visual dan aksesibilitas browser belum diverifikasi. Detector hanya advisory pre-existing: enam warna, 23 ukuran font dan lima radius. Tidak ada file pribadi, PDF, pengesahan, mutasi, penomoran/scheduler/tanggal efektif operasional. Kontrak UI dan batas fixture ada di `docs/employment-surface.md`. Bagian berikut merupakan riwayat modul terdahulu.

## Modul terbaru: Cuti & Pengajuan

Preview GET-only tersedia pada `/prototype/requests`, `/prototype/requests/{requestId}`, `/prototype/leave-balances`, dan `/prototype/team-calendar`. `PrototypeRequests` menyediakan delapan pengajuan dan ledger lima profil. Filter query di-whitelist. Detail mempertahankan konteks daftar atau saldo; shift sumber memakai tanggal/karyawan yang sesuai. Menu global kini lima tujuan dengan tambahan Pengajuan, tetap tanpa sidebar dan membungkus pada layar sempit.

Kuantitas saldo menggunakan integer unit setengah hari. Nadia memiliki kredit 24 unit, debit 6 dan reservasi 4: tersedia 14 unit atau 7 hari. Arif memiliki reservasi 0,5 hari; Dimas memiliki pasangan reservasi/pelepasan setelah penolakan. Nilai 12 hari kredit merupakan contoh sintetis, bukan hak cuti produksi. Usia pengajuan terpisah dari usia tahap aktif. Pemohon tidak diberi aksi persetujuan atau perubahan status.

`approvedLeaveDates()` menjadi sumber bersama contoh cuti Fajar 19 Oktober dan Nadia 23 Oktober bagi rekap dan kalender. Cuti tertunda tidak mengubah ketersediaan. Controller kalender hanya mengirim label ketersediaan dari roster/izin disetujui, bukan request payload, alasan pribadi atau diagnosis. Koreksi COR-026 belum mengubah raw event; lembur OVT-007 masih rencana; tukar shift SWP-003 ditarik dan roster tetap utuh.

Build, Pint, kompilasi Blade, HTTP serta 27 tes dengan 210 assertions lulus. Tidak ada endpoint mutasi, transaksi saldo, notification atau workflow operasional. Browser belum tersedia pada tahap sebelumnya sehingga visual/aksesibilitas belum disahkan. `docs/requests-surface.md` mencatat batas fixture dan kontrak UI. Bagian di bawah merupakan riwayat modul terdahulu.

## Modul terbaru: Jadwal & Kehadiran

Preview GET-only tersedia pada `/prototype/attendance`, `/prototype/schedules`, dan `/prototype/attendance/{record}`. `PrototypeAttendance` menyediakan 70 baris sintetis untuk lima karyawan, 12–25 Oktober 2026, pada tiga cabang WIB. Snapshot tetap 20 Oktober 2026 pukul 08.45. Filter GET memakai whitelist dan batas tanggal; perpindahan tab serta tautan kembali mempertahankan karyawan, cabang, dan periode asal. Menu global kini empat tujuan dengan tambahan Kehadiran, tetap tanpa sidebar.

Nadia pada shift malam 19 Oktober pulang pada 20 Oktober, tetap satu tanggal kerja 19 Oktober. Status Hadir tetap disertai terlambat 12 menit dan pulang awal 10 menit; durasi contoh 398 menit setelah istirahat 60 menit. Nadia/Raka pada 18 Oktober belum lengkap; Dimas pada 19 Oktober libur; Fajar pada 19 Oktober cuti; percobaan lokasi Dimas pada 15 Oktober ditolak dan hasil akhirnya Tidak hadir; Dimas pada 20 Oktober sedang bekerja. Shift mendatang berstatus Belum mulai. Tidak ada engine operasional, pengambilan GPS/kamera/foto, publikasi roster, pengajuan koreksi atau approval. Durasi bukan otomatis lembur terbayar.

Build, Pint, kompilasi Blade, HTTP, serta 21 tes dengan 140 assertions lulus. Review statis tidak menemukan penghambat kode. Browser CUA tetap tidak tersedia, sehingga tampilan dan interaksi desktop/mobile belum diverifikasi. Detector hanya menghasilkan advisory skala desain: enam warna lama, 21 ukuran font dan empat radius; drift di luar scope tidak diperbaiki. `docs/attendance-surface.md` mencatat kontrak dan batas fixture. Bagian di bawah adalah riwayat tahap sebelumnya.

## Modul terbaru: Karyawan & Organisasi

UI baca saja tersedia pada `/prototype/employees`, `/prototype/employees/{employee}`, `/prototype/organization`. Fixture `PrototypePeople` berisi 12 profil, 6 unit termasuk Direksi, 3 cabang. Filter/sort GET menggunakan whitelist validasi Laravel. Profil menampilkan penugasan, relasi atasan/laporan langsung, status dan timeline sintetis. Organisasi menghitung ACTIVE+OFFBOARDING, mengabaikan PREBOARDING/ENDED pada jumlah bekerja namun tetap menyimpan profil di direktori.

Shell diekstrak ke `components/app-layout.blade.php`. Menu global tetap tiga tanpa sidebar: Ringkasan, Karyawan, Organisasi. Tugas/Agenda menjadi tautan lokal dashboard; highlight global dari route server, bukan hash. Prototipe tetap fail-closed dengan middleware/flag sebelumnya. Tidak ada mutasi atau integrasi database. Filter direktori dipertahankan saat berpindah ke profil atasan/laporan langsung lalu kembali; regresi ini sudah diuji dan diperiksa ulang. Daftar 12 profil dan agregat dashboard adalah fixture terpisah, dijelaskan di footer.

Build, Pint, kompilasi Blade, 14 tes/67 assertions dan HTTP terhadap dashboard/direktori/organisasi/profil berbagai status berhasil. Verifikasi browser tetap belum tersedia; jangan mengklaim UI/aksesibilitas teruji dari sumber saja. Detector menghasilkan advisory nilai desain yang sebagian sudah ada sebelum modul; tidak dilakukan perbaikan drift di luar scope.

Pengguna meminta fondasi dahulu agar dapat mengatur GitHub sebelum melanjutkan UI dengan model lain. UI keseluruhan belum dikerjakan pada tahap ini.

## Revisi terbaru: Ruang Tim

Pengguna menolak Fokus Tugas karena menu aktif hanya tiga dan meminta UI modern yang tidak generik. Shell kini navigasi horizontal tanpa sidebar, palet plum/putih-lavender tipis, kehadiran dan agenda berdampingan, daftar tugas lebar penuh di bawah. Ukuran teks dan hit area diperbesar. Filter dan detail inline tetap tersedia; tidak ada perubahan akses atau backend. Dasarkan shell pada jumlah menu yang aktif, bukan seluruh roadmap. Bagian Fokus Tugas di bawah adalah riwayat tahap sebelumnya. Build Vite, kompilasi Blade, enam tes/13 assertions dan HTTP 200 telah diperiksa ulang setelah revisi. Review statis menemukan status navigasi yang bisa tertinggal saat hash/history berubah; sudah diperbaiki dan diterima pada review perbaikan. Browser CUA tetap kosong sehingga tampilan desktop/mobile belum diverifikasi secara visual. Advisory detector mengenai palet/type muncul terhadap DESIGN.md lama dan ditangani melalui pembaruan dokumentasi desain yang diotorisasi pengguna.

## Pembaruan: dashboard dan navigasi

Pengguna selesai push GitHub dan meminta lanjut, lalu memilih dashboard HR/navigasi sebagai tahap berikut. Branch aktif `dev`. PHP sudah 8.5.11 dan Composer install/check-platform-reqs berhasil. Bagian inisialisasi di bawah merupakan catatan historis.

UI Fokus Tugas tersedia pada `/prototype/dashboard`: sidebar sage terang, ringkasan kehadiran, antrean yang bisa dicari/difilter, detail inline, agenda, dan navigasi mobile. Fixture hanya baca di `app/Support/PrototypeDashboard.php`; tidak ada koneksi ke model bisnis atau aksi keputusan. Flag `HRIS_UI_PREVIEW_ENABLED` harus true, lingkungan local/testing; production tetap ditolak saat flag aktif. `.env` lokal menggunakan session/cache file agar pratinjau tidak membutuhkan PostgreSQL. Konfigurasi DB default tetap PostgreSQL.

Pemeriksaan tahap UI: build Vite, kompilasi Blade, PHP syntax, Composer platform/validate, Pint, HTTP 200 dan 6 tes (13 assertions) berhasil. Detector desain mengembalikan `[]`. Browser CUA tidak tersedia (inventory kosong); screenshot desktop/mobile dan pengujian interaksi browser belum dilakukan. Tidak ada klaim kelulusan aksesibilitas atau visual. Login, izin, domain R0 dan instalasi demo terisolasi masih pekerjaan lanjutan.

## Kondisi proyek

- Scaffold resmi `laravel/laravel` v13.0.0 di root HRIS, Laravel dan Livewire dikunci per patch di composer.lock.
- Blade, Tailwind/Vite bawaan, PostgreSQL, locale `id` dan fallback `en`.
- Git branch `main`; remote, commit awal dan push belum dibuat.
- PRD tetap utuh, PRODUCT.md merangkum konteks, salinan selector ada di docs/frontend-skill-selector.md.
- Halaman default Laravel dipertahankan. Auth, izin, demo, dan seluruh modul bisnis belum diimplementasikan.

## Runtime

PHP PATH saat inisialisasi adalah 8.2.30. Laravel 13 dan PHPUnit 12 memerlukan PHP 8.3+. Composer hanya me-resolve lockfile memakai config.platform.php=8.3.0, belum memasang atau menjalankan paket PHP. Ini tidak membuat aplikasi kompatibel dengan PHP 8.2. Gunakan runtime sesuai lalu jalankan composer install dan composer check-platform-reqs.

PostgreSQL belum dihubungkan, migrasi dan tes PHP belum dijalankan. Gunakan database terpisah hris_testing untuk pengujian. Flag demo false belum merupakan implementasi isolasi/gate DEMO-01–06.

## Pemeriksaan selesai

- composer validate --strict: valid.
- npm install: berhasil, audit melaporkan 0 vulnerabilities saat inisialisasi.
- npm run build: berhasil (Vite 7.3.7).
- Pengecualian Git untuk .env, .env.testing, vendor, node_modules dan public/build diverifikasi.
- Tidak ada validasi browser/UI HRIS karena UI belum dibangun. Tes PHP tertunda sampai runtime sesuai tersedia.

## Skill dan kelanjutan

Skill sumber di `C:/Users/Faiz Ismail M/.agents/skills/`. Folder impeccable, make-interfaces-feel-better, transitions-dev, transitions-polish dan GSAP sejajar. Jika pindah mesin, ikuti lokasi yang diberikan sesi baru. Salinan selector tidak memasang skill asli.

Pilih impeccable untuk desain UI operasional (mode Operate). SKILL.md asli dan referensi init sudah dibaca. Launcher context gagal karena cache engine belum tersedia dan lokasi cache pengguna tidak dapat ditulis; Markdown skill berhasil dibaca dan konteks PRD dipakai langsung. PRODUCT.md tersedia, belum ada DESIGN.md atau arah visual yang disepakati.

Setelah pengguna mengatur GitHub dan meminta lanjut, periksa runtime, baca PRD dan selector, tetapkan arah desain, lalu bangun UI sesuai prioritas R0–R3. Baca referensi skill sebelum edit UI. Tambahkan transitions-dev jika interaksi membutuhkan, polish setelah UI ada. GSAP hanya jika kebutuhan animasi mendukung. Hindari menggandakan Alpine bawaan Livewire.

Navigasi mengikuti bagian 17 PRD, izin dan status rilis. Modul belum tersedia disembunyikan dari pekerjaan sehari-hari; mockup harus jelas berstatus prototipe, tanpa mengklaim keamanan atau data operasional telah berfungsi.

## Backend API 21 Oktober — kehadiran dan ledger

API roster/clocking, koreksi, swap berurutan, cuti dengan reservasi/ledger/reversal, lembur aktual serta review/close dengan snapshot tersedia. Full suite PostgreSQL: 141 tests / 2137 assertions; Pint dan diff check lulus. Tidak ada fresh pada database pengembang. Lihat ATTENDANCE_LEAVE_BACKEND.md untuk aturan dan sisa cakupan. UI statis belum tersambung; seluruh backend belum selesai. Tahap berikut profil sensitif, dokumen/lifecycle/aset, payroll, impor/report dan demo terisolasi.

## Profil sensitif dan suspension API

Lima tes profil privat lulus (68 assertions); identity/account regression 25 tests lulus sebelum tambahan uniqueness test. API memisahkan verifikasi HR dan Finance, enkripsi data/revisi, HMAC identifier unik, optimistic version dan custom fields bertipe. Suspension management dan account access API memakai MFA, idempotensi, security-version dan perlindungan admin terakhir. Detail PRIVATE_PROFILES.md. Belum menyelesaikan seluruh backend.

## Dokumen, lifecycle dan aset — backend API

Full suite PostgreSQL lulus 152 tests / 2303 assertions; Pint, composer validate --strict, composer check-platform-reqs dan diff check lulus. PDF privat immutable, nomor transaksi, kontrak as-of/nonoverlap/reminder, rehire/activation gate, scheduled exit/runtime revoke dan asset custody/settlement tersedia. Batasan acceptance dicatat pada DOCUMENTS_LIFECYCLE.md. PHP CLI ZIP diaktifkan untuk dukungan spreadsheet; proses web yang sudah berjalan perlu restart agar extension ikut termuat. Tidak ada akun/data produksi atau email nyata yang dibuat. Seluruh backend belum selesai; payroll/impor/demo serta kelengkapan R1/R2 masih dilanjutkan.

## Payroll draft API

Pengguna mengonfirmasi belum ada kebijakan: siapkan draft. Tidak ada policy/tarif produksi yang di-seed/disahkan agent. API kompensasi efektif, kalkulasi integer/rational, policy expert gate, source invalidation, final snapshot/PDF privat, bank-template confirmation/export dan payment outcomes tersedia. Tiga tes PayrollTest lulus123assertions sebelum hardening trigger tambahan. Lihat PAYROLL_BACKEND.md untuk basis prorata, minor unit, aturan yang didukung dan sisa cakupan regulasi/adjustment/claim. Seluruh backend belum selesai.

## Reimbursement API dan kanal settlement

16 tests / 308 assertions untuk expense/payroll/workflow lulus. Bukti wajib, alur manager-Finance, limit kategori/eligibility, duplicate-warning dan finance verification evidence tersedia. Unique allocation mengunci kanal payroll/reimbursement, settlement parsial append-only dan outcome payroll terhubung tanpa debit ganda. Payroll membayar salary dahulu lalu claim menurut allocation ID. Detail EXPENSE_BACKEND.md. Masih perlu import/report/demo dan kelengkapan acceptance yang dicatat per modul.

## Impor atomik dan staging

5 ImportTest / 92 assertions lulus setelah perbaikan UUID namespace dan roster receipt. Dukungan CSV/XLSX, upload-map-preview-commit, checksum, error per baris, encrypted staging dan background worker dengan recheck izin. Seluruh jenis diuji: masters, employee/identity draft, roster draft, opening leave ledger dan compensation draft. Duplicate national ID/changed source tidak membuat profil parsial; formula XLSX ditolak. IMPORTS_BACKEND.md mencatat batas ukuran, data format dan sisa UAT/load.

## Reports/export/search milestone

Full suite lulus167 tests / 2668 assertions sebelum ekstensi operasi berikutnya. Report JSON domain, closed attendance/overtime snapshot drill-down, encrypted CSV/XLSX exports dengan24jam expiry/recheck team-scope serta pencarian ringkasan berizin tersedia. CSV formula-neutralized dan XLSX explicit-string. Histori exit baru memakai offboarding_from. REPORTS_SEARCH_BACKEND.md mencatat batas sinkron, query-plan/scale dan filter finansial historis yang perlu dilengkapi. Belum full acceptance backend.

## Delegasi/kalender/notifikasi

WorkflowExtensionsTest5 tests /62 assertions lulus; WorkflowOperationsTest10/100 dan swap regression juga lulus. Delegasi subjek eksplisit dengan provenance decision, future activation/revoke runtime, SLA kalender kerja/holiday/timezone, quiet hours, opt-out dan daily digest nyata tersedia. Inbox/delivery recheck access; expired export byte cleanup dijadwalkan. Detail WORKFLOW_EXTENSIONS.md. Belum ada SMTP nyata; default policy lama elapsed24/48jam harus dikonfigurasi eksplisit untuk SLA kerja.

## Attendance policies dan supersession roster

AttendancePolicyTest3/47 serta regression attendance/import/swap/close lulus. Remote/field policy server-side, future roster replacement dengan source/version/history guard, minimum staffing interval yang mengurangi breaks, warning acknowledgement/blocking policy dan branch-timezone close tersedia. ATTENDANCE_POLICIES.md mencatat konfigurasi dan batas eligibility khusus. Tidak ada angka minimum staffing bawaan.

## Administrasi karier, kontrak dan clearance

Full suite lulus178 tests /2830 assertions; Pint dan diff-check lulus. Career workflow wajib bukti dan approval independen company HR, tanpa bypass review lama. Contract review/rekomendasi, renewal berantai dan pembatalan kontrak terjadwal mempertahankan PDF/history. Pergantian owner task, reminder/escalation, snapshot closure offboarding dan settlement Finance tersedia. Migrasi270000 sudah diterapkan ke database dev dan hris_testing tanpa reset data. DOCUMENTS_LIFECYCLE.md mencatat batas exit/reconciliation/alumni. Backend keseluruhan dan integrasi UI belum selesai; BACKEND_DELIVERY.md diperbarui agar status payroll/import/report tidak lagi tertinggal.

## Jadwal entitlement cuti

LeaveGrantSchedulesTest3 tests /32 assertions dan MigrationFreshTest lulus (gabungan4/50); regression cuti/workflow/lifecycle28/348 lulus sebelum penambahan pilihan expiry_rule eksplisit. Pint/diff-check lulus. Jadwal annual/monthly draft→independent HR approval→server-date catch-up memakai ledger dan run unik append-only. Grant produksi tidak di-seed. LEAVE_GRANT_SCHEDULES.md mencatat expiry calendar-year-end yang wajib dipilih, penerima eksplisit, stop tanpa clawback, overlap guard dan batas carry-over/expiry posting. Migrasi280000 diterapkan dev/test tanpa reset.

## Rekonsiliasi expiry dan transfer hak cuti

20 tests /260 assertions cuti/report/migration lulus, Pint/diff-check lulus. Debit adjustment tidak mengambil reserved/used credit; source-linked carry-over satu transfer per lot menulis debit sumber dan kredit tahun berikutnya atomik. Scheduler append-only expiry/reversal menyesuaikan release/cancellation setelah expired sambil menjaga reservation tanggal kerja lama. Balances, reservation allocator dan report as-of memakai kapasitas setelah adjustment. Migrasi290000 diterapkan dev/test tanpa reset.

## Payroll corrections dan variation gate

Payroll/expense/migration8 tests /275 assertions lulus, tambahan next-period/variation1 test /47 assertions lulus. Koreksi source-linked pending→Finance approval, target next-period/off-cycle, source/version/checksum guards, private review API, reset draft beralasan dan mandatory variation acknowledgement tersedia. Off-cycle tidak membayar salary/claim lama lagi. Signed money formatting diperbaiki. Kebijakan pajak/net correction tetap memerlukan manusia berwenang; tidak ada parameter produksi yang disahkan agent. Migrasi300000 diterapkan dev/test tanpa reset.

## Demo boundary dan scenario operations

DemoIsolationTest +MigrationFreshTest3 tests /48 assertions lulus; regression attendance/document/workflow/payroll/migration27/534 lulus sebelum tambahan storage guard/banner assertions. Per-instance UUID/database identity, private storage/session/queue/cache, durable marker restore guard, token-controlled personas/time/GPS, SIMULATED attendance, watermarked docs/slips/reports dan exact-target reset tersedia. Seed hanya data sintetis dan payroll draft. Runtime produksi tidak mendaftarkan route demo. DEMO_BACKEND.md mencatat provisioning, token/CSRF, satu instalasi per skenario, clock hanya attendance/documents dan sisa hosting/UI kalender virtual. Migrasi310000/320000 diterapkan tanpa reset dev/test; reset regression hanya database demo acak disposable.

## Opening payroll dan sumber YTD

14 tests /430 assertions opening/payroll/import/migration lulus. Template CSV per kind dapat diunduh berizin. Opening import menghasilkan draft, bukan histori final palsu; approval Finance dengan evidence, unique employee/year/source dan overlap guard. Konteks YTD mengambil opening approved + final regular dengan hash/version, klasifikasi tax_component dan optional bounded subtract_prior_tax, tanpa mengarang tarif. Formula kumulatif lintas tahun/legacy tanpa klasifikasi diblokir. Pembacaan nominal opening/history privat bagi Payroll/Finance. Migrasi330000 diterapkan dev/test tanpa reset. Detail IMPORTS_BACKEND.md/PAYROLL_BACKEND.md.

## Batch reimbursement dan instruksi retry

12 tests /422 assertions expense/payroll/migration lulus; Pint/diff-check lulus. Finance menyiapkan batch, Finance independen mengesahkan, template confirmed dengan payment_id wajib per item, encrypted CSV/checksum/control total, append-only paid/failed outcomes dan pending-instruction lock tersedia. Retry setelah rekonsiliasi hanya memuat sisa belum lunas; settlement bebas diblokir pada batch aktif. Cancel/rebatch beralasan tidak menghapus pembayaran. Test dua klaim paid/failed membuktikan retry berisi satu item. Migrasi340000 diterapkan dev/test tanpa reset. Detail EXPENSE_BACKEND.md; tidak ada instruksi bank nyata dikirim.

## Exit source reconciliation dan katalog operasional

14 tests /226 assertions lifecycle/leave/catalog/career lulus sebelum penambahan metadata summary exit. Final exit menarik pending requests terdampak, melepas reservation, reversal approved leave masa depan, membatalkan future OT dan supersede roster periode lama. Snapshot unik append-only merujuk keputusan HR, tanpa menghapus history/asset obligations. Catalog whitelist berizin dan reviewer lookup disediakan untuk integrasi form. Migrasi350000 diterapkan dev/test tanpa reset. Detail DOCUMENTS_LIFECYCLE.md.

## Penutup domain: exceptions, retrospective review dan privacy

Full suite lulus200 tests /3298 assertions. Sesudah pembatasan source_type carry_over pada grant manual, LeaveLotAccountingTest tambahan lulus2/32. Notice exception HR tetap menjaga employment/tenure/evidence/saldo dan approval independen; HR cancellation target divalidasi. Career retrospective memerlukan reviewer HR dan Finance berbeda, evidence dan annotation immutable tanpa overwrite closed history; aplikasi placement prospektif. Preview dokumen privat tidak menerbitkan nomor/PDF. Snapshot placement harian/referensi off-cycle memperbaiki laporan joiner/mover; golden join/exit/change menghasilkan30000sen tepat. Reassignment Finance tidak dapat diambil HR. Self grant/adjustment cuti dan own asset settlement ditolak. Migrasi360000 diterapkan dev/test tanpa reset; cipher audit tetap privat. Prototype fixture local tetap dapat ditinjau tanpa query operational boundary.

## Verifikasi operasi dan handoff backend

ConcurrentLeaveTest menjalankan dua proses PHP sungguhan dengan start barrier dan database disposable; satu submit berhasil, satu422, retry tidak menambah reservation. BackupRestoreTest memakai pg_dump/pg_restore PostgreSQL17 asli ke database disposable lain, memeriksa dekripsi ciphertext dan trigger audit, lalu cleanup target yang dibuatnya. hris:check read-only lulus pada instalasi lokal; strict production gate diuji menolak config testing. Default queue retry_after kini390detik, melampaui job import300detik; sesuaikan supervisor sesuai OPERATIONS.md.

Validasi akhir: suite200/3298 dan targeted carry-over2/32 lulus, Pint lulus, composer validate --strict serta check-platform-reqs lulus pada PHP8.5.11, npm run build lulus, diff-check bersih. API workspace tercatat114rute pada verifikasi21Oktober2026. .env di-ignore dan tidak dilacak. README/PROJECT_STATUS/BACKEND_DELIVERY/BACKEND_ACCEPTANCE diperbarui agar status fondasi lama tidak disalahartikan sebagai cakupan API saat ini.

Tahap berikut: integrasi UI/session/CSRF/version/idempotency dan UAT lintas peran; konfigurasi/pengesahan kebijakan perusahaan, SMTP/worker/host dan backup produksi. Tidak mengklaim statutory golden payroll, UI render acceptance, production RPO/RTO atau deployment telah disahkan. Demo masih satu instalasi per skenario, dengan Clock attendance/documents; bounded engines/scale limits dicatat per modul.

## Integrasi layar pengajuan

/app/requests sekarang operasional melalui layanan domain yang sama dengan API: cursor/filter daftar berizin, detail/status/tahapan, draf cuti/pembatalan/lembur/koreksi, bukti privat dan submit/withdraw/independent decisions. Jenis workflow lain hanya status; approval finansial tidak ditampilkan lewat form generik. Medical reason/evidence dibatasi pemilik/HR dengan predicate privat yang sama seperti download. Waktu datetime-local ditafsirkan pada timezone shift; optimistic version dan UUID mutation dijaga server, konflik mempunyai recovery muat ulang.

WorkflowWorkspaceTest +WorkflowOperationsTest +LeaveDomainTest:22tests/279assertions lulus; npm build, Blade cache dan Pint lulus. Browser inventory kosong sehingga QA visual/keyboard 360/1440 masih belum diverifikasi. Tidak ada migrasi/reset database dev, perubahan .env atau pengesahan kebijakan produksi. Tahap lanjut: notifikasi/search, attendance/roster, administration/finance/import/report UI.

## Integrasi notifikasi dan pencarian

/app/notifications menampilkan inbox operasional dengan pemfilteran akses runtime, cursor, status baca, tindakan baca/arsip dan tautan pengajuan. /app/search memakai pembacaan API yang sama untuk employee/request/document/asset, tautan halaman operasional untuk karyawan/pengajuan dan private document download; aset hanya ringkasan sampai detail UI terhubung. Adapter presentasi memanggil reader controller API tanpa HTTP dispatch; tidak menggandakan query scope. Navigasi horizontal mendapat Pengajuan/Notifikasi/Cari. Fixture prototype tetap terpisah.

WorkflowWorkspaceTest kini6test termasuk pengajuan, medical privacy, notifikasi/search lintas pengguna, zona shift dan form validation. UI attendance/GPS/roster, domain administration/finance/import/report masih perlu integrasi; QA browser tetap belum dapat dilakukan karena inventory browser kosong.

Verifikasi penutup integrasi:53tests/619assertions lulus (WorkflowWorkspaceTest, WorkflowOperationsTest, LeaveDomainTest, IdentityFoundationTest dan filter ReportsTest termasuk preview reports); npm build, Pint, Blade cache dan diff-check lulus. Layar tersedia dari navigasi /app setelah login/MFA. Tidak mengubah konfigurasi .env atau menerapkan kebijakan perusahaan.

## Integrasi kehadiran dan roster

/app/attendance menampilkan jadwal/detail/proyeksi sesuai scope pada work_date, actual/provenance tanpa koordinat privat, private photo upload, clock-in/out melalui waktu server dan kebijakan lokasi persisted. Geolocation hanya setelah tindakan pengguna; remote tidak meminta lokasi. Network uncertainty mempertahankan payload/key percobaan yang sama; domain rejection membuat key baru. Izin GPS/HTTPS, session expiry, accuracy/geofence, photo dan empty-window ditangani tanpa koordinat manual/simulasi produksi. Tautan koreksi membawa pilihan shift sendiri.

/app/schedules untuk company HR/team manager: pola employee/date/working days/template→draft→review/publish; service mengecek ulang scope/version/checksum/placement/coverage. Tidak mempublikasikan batch campuran scope secara parsial. Peringatan staffing memerlukan acknowledgement; semua hari libur memerlukan konfirmasi eksplisit. Pilihan karyawan dibatasi50 dengan pencarian; template memakai pagination katalog; rows detail50. Replacement/import/period close/actual OT belum terhubung lewat layar ini.

AttendanceWorkspaceTest +AttendanceDomainTest +AttendancePolicyTest +WorkflowWorkspaceTest lulus22tests/388assertions; tiga tes node:test membuktikan retry identical-key/coordinates setelah uncertain network, GPS denial tanpa request dan fresh-key rejection/remote tanpa GPS. Pengujian JavaScript memakai harness DOM/network, belum QA browser/perangkat nyata. Tidak ada konfigurasi perusahaan atau .env yang diubah.

## Konfigurasi lokasi dan template dari UI

HR company dapat mengisi lokasi/cabang/Indonesia timezone/onsite-field-remote/geofence/photo serta versi template shift malam, jendela clocking, toleransi, half-day split dan hingga8rentang istirahat. Tidak ada angka policy yang dipilih/disahkan otomatis; existing typed domain validators dan append-only snapshot roster dipertahankan. Kode template yang sama membuat versi baru, lokasi memakai kode unik. Manager/team HR scope tidak mendapat perubahan master company. Daftar master20baris/pagination; pilihan branch/location masih dropdown aktif company. Entri istirahat memakai menit sejak awal shift, dengan contoh lintas malam; half-pair/overlap/out-of-shift ditolak. Beranda mendapat tautan kehadiran.

QA perangkat nyata tetap tertunda; harness JavaScript kini juga memverifikasi respons HTML419 meminta login ulang. Tahap berikut UI actual overtime/period close/replacement, administrasi, finance serta import/report.

Verifikasi akhir modul:24tests/423assertions PHP (AttendanceWorkspaceTest, AttendanceDomainTest, AttendancePolicyTest, WorkflowWorkspaceTest) dan4tes JavaScript lulus; npm build, Blade cache, Pint dan diff-check lulus. Tidak ada migrasi/reset database dev, email/transaksi nyata atau perubahan .env. Push bertahap ke dev; QA render/perangkat browser belum disahkan.

## Penutupan periode dan lembur aktual dari UI

/app/attendance-periods untuk HR Admin company menyediakan create (tanggal akhir inklusif di UI, dikonversi exclusive-end domain), review/blockers, close immutable snapshots, revisi lama, dan reopen beralasan sebelum payroll final. GET membaca hasil review dari mutation_receipts dengan period ID/version tepat; tidak menjalankan review atau menaikkan versi. Close tetap mengumpulkan dan mengecek ulang sumber/checksum. Blocker dan snapshot masing-masing50baris/pagination, dengan tautan ke attendance/overtime/roster yang perlu diperbaiki. Reopen kini mempertahankan alasan terenkripsi pada audit; teks tidak dicatat plaintext. Tidak ada perubahan schema.

/app/overtime menyediakan daftar/detail dengan akses owner/current manager/HR/scope workflow, datetime-local aktual pada timezone shift, independent verification dan HR-only exception saat attendance tidak mendukung. Stale attendance version ditandai dan dapat diverifikasi ulang; period closed mengunci form, server tetap memeriksa. Catatan exception tidak didekripsi ke UI. Pengajuan overtime dan halaman attendance menautkan aktual; halaman attendance menautkan review periode bagi HR company. Snapshot revisi dipertahankan pada reopen, sedangkan payroll final menolak reopen.

CloseOvertimeWorkspaceTest mencakup lifecycle close/reopen/reclose, review GET tanpa perubahan versi, snapshot/revision/payroll lock, unresolved/gap/source change, actual2jam dari planned3jam, replay, self/manager/scope denial, private HR exception dan stale verification setelah correction approved. Browser inventory masih kosong; tidak ada klaim QA render/perangkat atau UAT produksi. Tahap berikut replacement/kalender/hak cuti UI lalu administrasi, finance dan import/report.

Verifikasi akhir:24tests/502assertions lulus untuk CloseOvertimeWorkspaceTest, OvertimeCloseTest, AttendanceWorkspaceTest dan WorkflowWorkspaceTest; npm build, Pint, Blade cache dan diff-check lulus. .env tidak diubah/dilacak dan tidak ada migrasi/reset database dev atau transaksi/email nyata.

## Pengganti roster dan kalender tim

Form /app/schedules/replace/{shift} membuat draf sumber/version-linked satu tanggal karyawan. Jadwal asli masih published sampai publikasi pengganti; engine tetap memblokir sumber mulai/closed/terkait leave/OT/correction/events dan menyimpan superseded history + notifikasi. Actor dibatasi company HR/team manager sesuai work date, employee biasa tidak mendapat override. Preview draf menampilkan alasan penggantian.

/app/team-calendar memakai CalendarService yang sama dengan API, daftar50baris/pagination dan batas31hari/5000kandidat. Kalender tidak membuka alasan/bukti cuti; hanya scheduled/day_off/unavailable. /app/work-calendar untuk company HR membuat versi holiday/working day eksplisit, daftar hanya versi terakhir setiap branch/date; tidak otomatis menulis roster. CalendarReplacementWorkspaceTest4/58 lulus termasuk privacy, replay, versi, scope dan source-start guard. Pengelolaan hak cuti dilanjutkan pada putaran yang sama.

## Hak cuti dari UI, putaran yang sama

/app/leave-balances membaca hak sendiri; /app/leave-management untuk HR Admin company menyediakan pencarian lot/ledger, jenis cuti versi baru, manual referenced credit, debit dan source-linked carry-over. Semua kebijakan/durasi/tahun/tanggal/referensi diisi manusia; tidak ada grant otomatis atau asumsi8jam/hari. LotAccounting::balance menjadi pembacaan bersama API/self/admin dengan format API lama, melindungi reservasi/pemakaian dan memisahkan expired/future credit. Tanggal akhir form inclusive dikonversi exclusive-end domain.

/app/leave-management/schedules menyediakan annual/monthly draft, penerima eksplisit, expiry calendar-year-end, independent non-recipient HR approval, stop tanpa clawback dan realisasi/grant links. Superseded type/revoked approval authority memunculkan pause notice, tidak menerbitkan grant baru. Scheduler hris:dispatch-operations tetap tanggung jawab operator; tidak dijalankan dari UI approval. Evidence/stop reason didekripsi hanya setelah company HR permission; medical leave reasons tidak masuk kalender. Pilihan employee50 dengan pencarian; daftar lot/schedule/ledger/runs dipaginasi20–30.

LeaveWorkspaceTest5/118 lulus untuk manual/replay/scope, reserved420→available540→debit240→available300→withdraw→available720, self-grant/self-debit denial, expired linked carry sekali, independent approval/policy expiry rule, real dispatch-once/stop dan superseded-type pause. Tidak ada reset DB dev/migrasi/perubahan .env; UI administrasi/finance/import/report dan workflow khusus swap/HR notice-exception belum dilanjutkan. QA browser/mobile/perangkat nyata masih tertunda.

Verifikasi akhir putaran:46tests/853assertions lulus (LeaveWorkspaceTest, LeaveDomainTest, LeaveLotAccountingTest, LeaveGrantSchedulesTest, CalendarReplacementWorkspaceTest, AttendancePolicyTest, AttendanceWorkspaceTest, WorkflowWorkspaceTest, CloseOvertimeWorkspaceTest). npm build, Pint, Blade cache dan diff-check lulus. Browser inventory kosong. Roster/calendar sudah push70262a3; hak cuti di-commit/push terpisah agar histori modul tetap bertahap.

## Penutupan integrasi operasional — 21 Oktober 2026

Commit 2219727 menghubungkan formulir administrasi/finance/data/settings ke adapter domain; commit 34088e9 melengkapi pembacaan bukti/mandat/format, demo operator, browser tests dan izin ekspor auditor eksplisit. Keduanya dikembangkan pada dev.

Ruang kerja /app/modules memuat 124 formulir/tindakan tambahan, seluruh 20 jenis workflow dapat dibuka/diproses sesuai izin. Profil dan proposal kompensasi memakai pembaca privat/audit; referensi dipilih lewat lookup berizin. Import memakai header aktual, preview/checksum/version. Finalisasi payroll tetap membutuhkan kebijakan disahkan dan sumber valid. Hak baca auditor tidak otomatis mengizinkan ekspor; perubahan izin menaikkan security version dan mencabut file lama.

Verifikasi: regresi penuh 246 tests / 4510 assertions lulus pada hris_testing dan database disposable berpenjaga. Setelah koreksi tautan instruction-versus-batch, targeted 14 tests / 567 assertions lulus. Tiga browser tests menjalankan JavaScript nyata terhadap fixture sintetis pada Chrome headless dengan profil terpisah; desktop dan viewport 390 px diperiksa. Screenshot tersimpan lokal pada .cache/operation-browser, tidak masuk Git. npm build, Pint, Blade cache, JS syntax, diff-check dan hris:check lulus. Chrome memerlukan eksekusi di luar sandbox GPU; Node 22+ diperlukan untuk WebSocket bawaan pada harness.

Migration 2026_10_21_370000 menambah auditor_export_enabled default false, diterapkan secara additive pada database pengembangan dan pengujian. Tidak ada reset database operasional, perubahan .env, pengiriman email/bank nyata atau deployment. .env tetap ignored/untracked.

Panduan terbaru: WORKSPACE_OPERATIONS.md, PROJECT_STATUS.md, BACKEND_DELIVERY.md dan OPERATIONS.md. Nilai/pengesahan kebijakan perusahaan, konfigurasi host/supervisor/SMTP/TLS/ACL/backup off-host, pengukuran load/RPO/RTO, perangkat nyata dan UAT pemilik kebijakan tetap keputusan serta bukti operator. Tiga fixture browser bukan recapture semua prototype atau pengesahan seluruh perangkat/aksesibilitas.

## Penutupan cakupan lokal — 23 Oktober 2026

Perbaikan UI kehadiran dan kelanjutan roster: /app/attendance mengganti daftar paragraf menjadi tabel semantik karyawan/tanggal kerja/shift/waktu efektif/status/durasi/detail, filter horizontal serta preset tanggal. Ringkasan status secara eksplisit hanya untuk halaman yang ditampilkan. Durasi menunggu pasangan waktu efektif, IN_PROGRESS diberi label Berlangsung; jadwal belum tercatat/libur tidak lagi ditampilkan sebagai Kerja 0 menit. Shift malam menampilkan +1 hari dan tanggal clock-out berikutnya. Daftar/detail roster juga memakai tabel dengan status draf/publikasi/shift diganti, staffing warnings serta formulir publikasi/checksum/version/acknowledgement asli. Navigasi Kehadiran tetap aktif untuk roster, lembur dan penutupan. Tabel mobile memiliki region horizontal berlabel/focusable dan petunjuk geser; halaman tidak overflow pada fixture yang diperiksa.

Verifikasi: 20 tes/422 assertions untuk AttendanceWorkspaceTest, CalendarReplacementWorkspaceTest dan CloseOvertimeWorkspaceTest; 7 tes browser/46 assertions lulus. Browser Chrome terpisah merender kehadiran dan roster pada 1440/360 px dengan CSS build aktual; screenshot ditinjau langsung. Kasus waktu efektif 398 menit tampil 6 j 38 mnt, shift terjadwal/libur tanpa pasangan waktu memakai tanda pisah, izin/clocking/geofence dan overnight tetap diperiksa. Build, Blade cache, Pint dan diff-check lulus. Hanya UI dan tes/dokumentasi yang masuk commit; seeder lokal tetap ignored, tidak ada migrasi, reset, perubahan password atau pengiriman nyata.

Keputusan terbaru pengguna: seeder akun/data portfolio, dokumentasi akun lokal dan tes seedernya tidak disertakan dalam versi terbaru repository. File tetap tersedia secara lokal dan masuk .gitignore; DatabaseSeeder publik hanya memanggil paket lokal opsional bila kelasnya tersedia pada environment local non-demo. Tidak ada perubahan, penghapusan atau pengulangan seeding pada database saat penarikan file dari Git. Bootstrap/provisioning manual tetap utuh. Penghapusan melalui commit baru menghilangkan file dari tree dev terbaru, bukan menulis ulang riwayat commit lama. Catatan seeder di bawah adalah riwayat implementasi sebelumnya, bukan daftar file yang masih dipublikasikan.

Seeder data contoh per bagian atas permintaan pengguna: Company, Branch, Department, Job, Employee, StaffUser, LeaveType, LeaveBalance, WorkLocation, ShiftTemplate, Roster dan WorkflowSeeder dengan guard LocalSeeder; LocalTestingSeeder mengatur urutan. DatabaseSeeder memanggil paket ini hanya pada local non-demo, sementara testing default/produksi tetap tanpa data contoh. UserSeeder mandiri tetap tersedia, provisioning/bootstrap manual tidak diubah. Master/mutasi memakai domain services, bukan insert yang melewati snapshot/ledger/validasi. Namespace LOCAL- dan nama Contoh/testing membedakan data sintetis. Kebijakan payroll tidak ditambah/disahkan.

Seeder telah dijalankan pada database pengembangan tanpa reset: 11 akun, 6 karyawan/periode/penempatan, 2 cabang, 2 departemen, 4 jabatan, 3 jenis cuti, 2 lokasi, 2 template, 84 shift published untuk 14 tanggal, 6 kredit/ledger dan 6 definisi workflow. Pengulangan lokal mempertahankan seluruh jumlah termasuk audit serta hash password; data lama tidak ditimpa. Tidak dibuat transaksi pengajuan, clocking, bukti medis atau email nyata. Akun staff baru memakai password acak yang hanya ditampilkan saat penciptaan dan tidak disimpan di Git.

Verifikasi: 27 tes/322 assertions lulus (LocalTestingSeederTest, UserSeederTest, MigrationFreshTest, IdentityFoundationTest), termasuk dropdown berdata nyata, cuti pengecualian HR → submit → persetujuan independen → alokasi used 480 menit, replay tanpa duplikasi, prasyarat seeder individual, guard produksi/demo dan bootstrap manual. Pint dan diff-check lulus. Panduan seeder per kelas serta cara mencoba pengajuan ada di LOCAL_TEST_DATA.md.

Koreksi kualitas UI setelah screenshot pengguna: CSS memang termuat, tetapi identity-layout operasional masih layout formulir sempit terpisah dari prototype. Implementasi baru menerapkan shell Ruang Tim horizontal bersama, menu akun native disclosure, pencarian/notifikasi, konteks perusahaan dan tanggal, form/panel bersama, beranda dengan scoped employee counts/recent people, antrean review terbatas dan panduan organisasi kosong. Direktori karyawan dan master organisasi memakai tabel semantik, filter/create/edit tetap terhubung ke controller nyata. Klaim MFA berhasil di beranda dihapus. Tidak ada data sintetis ditambahkan ke database lokal untuk mempercantik ringkasan. Halaman domain lain memakai shell/form bersama; belum berarti seluruh modul memiliki desain khusus setara prototype. Status proyek dikoreksi agar implementasi backend tidak disamakan dengan penyelesaian kualitas visual.

Verifikasi perbaikan UI: 53 tes aplikasi/999 assertions dan 6 tes browser/32 assertions lulus. Scope dashboard employee/platform/HR diperiksa, termasuk tidak bocornya data karyawan lain. Chrome headless memeriksa dashboard 1440/360 px serta fixture kosong, tabel dan formulir pada 1100/390 px; hasil capture ditinjau langsung dan tidak ada overflow halaman pada skenario tersebut. npm build, Blade cache, Pint, syntax JavaScript dan diff-check lulus. Launcher impeccable context tidak dapat berjalan karena engine/cache tidak tersedia; PRODUCT, DESIGN, selector dan referensi skill dibaca langsung, tanpa pemasangan engine atau perubahan data operasional.

Testing tanpa 2FA atas permintaan pengguna: HRIS_LOCAL_SKIP_MFA=true sudah diaktifkan pada .env lokal (tetap ignored), lalu config cache dibersihkan. MfaPolicy membatasi pengecualian ke environment local non-demo saja; middleware workspace dan adapter challenge Fortify memakai aturan yang sama. Secret/recovery MFA tidak dihapus, password dan otorisasi tidak dilewati. README dan .env.example menjelaskan cara mengaktifkan kembali. Empat tes lokal mencakup akun belum/enrolled MFA, password salah, batas environment/demo dan pemulihan kewajiban MFA; regresi identitas standar tetap lulus.

Pembaruan akun lokal: atas permintaan pengguna, UserSeeder menyediakan delapan akun aktif dengan mandat company, termasuk dua HR Admin untuk independent review. DatabaseSeeder memanggilnya hanya pada environment local non-demo; direct UserSeeder menolak produksi/demo. Email tercatat di README; password acak akun baru hanya ditampilkan di terminal, MFA tetap wajib. Akun yang sudah ada dilewati tanpa perubahan password/status/mandat; perusahaan yang ada dipertahankan. Tidak dibuat data karyawan atau secret MFA sintetis. Seeder telah dijalankan pada database pengembangan tanpa reset; 23 tes/228 assertions (seeder, identity/provisioning dan isolated migrate:fresh) lulus. Perintah bootstrap manual tidak diubah dan tetap berlaku sebelum perusahaan pertama tersedia.

Pengguna menegaskan tidak berencana deploy. Modul backend dan integrasi aplikasi lokal telah diimplementasikan; persyaratan penerimaan produksi di atas hanya berlaku jika kelak akan go-live. Kebijakan payroll tetap draft sesuai keputusan pengguna.

Commit c31fcba sudah di-push ke dev. composer run dev kini menjalankan server, worker, scheduler, log dan Vite sekaligus. Status worker/scheduler memakai heartbeat aktual. Paket deploy dan runner backup terenkripsi tersedia sebagai fasilitas opsional, belum dipasang atau dijalankan untuk backup nyata; tidak ada perubahan .env, pengiriman email/bank nyata atau pemasangan service OS.

Verifikasi akhir: 251 tes aplikasi / 4547 assertions dan 3 tes pipeline backup dengan cipher palsu khusus pengujian lulus. Pint, composer validate --strict, kompilasi Python, parse PowerShell, syntax Bash dan git diff --check lulus. Migrasi heartbeat diterapkan secara additive tanpa reset database. README dan PROJECT_STATUS menegaskan batas cakupan lokal agar checklist produksi tidak dianggap modul yang belum dibuat.
