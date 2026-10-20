# Payroll & Reimbursement

Scope: `/prototype/payroll`, `/prototype/payroll/{batch}`, `/prototype/payroll/{batch}/payslips/{employee}`, `/prototype/reimbursements` dan `/prototype/reimbursements/{claim}`. Mode Operate. Dunia Ruang Tim, data sintetis baca saja.

THESIS: Petugas membedakan perhitungan yang final dari uang yang benar-benar tercatat berhasil, tanpa menganggap persetujuan atau ekspor sebagai bukti bayar.

OWN-WORLD: palet plum, Segoe UI, panel putih, native GET filters, tabel dan focus ring incumbent. Header global kini tujuh tujuan; Keuangan membuka payroll dan reimbursement. Pada lebar 1280 px ke bawah navigasi menempati baris sendiri dan membungkus; tanpa sidebar atau menu tersembunyi. Tidak ada raster/animasi baru.

STORY: filter batch, periksa versi sumber dan penghambat, baca penerima snapshot, lalu pratinjau slip yang telah final dan dirilis. Klaim menelusuri kanal tunggal, nominal asal, settlement berhasil/gagal dan sisa bagian yang belum dibayar. Same-register filters dipertahankan; lintas register payroll/klaim tidak membawa filter yang berbeda makna.

FIRST VIEWPORT: judul/snapshot, dua tujuan lokal, register batch atau klaim. Detail batch menempatkan status perhitungan dan status pembayaran terpisah sebelum pemeriksaan/penerima. Signature interaction: batch September sudah final, tetapi transfer Arif gagal dan pembayaran Intan baru sebagian. Slip HTML menjelaskan komponen dan referensi sumber, bukan bukti transfer.

FORM: perluasan sistem operasional yang disetujui. Disclosure native membuka rincian kerja sebelum final, tanpa mengaktifkan slip. Tidak ada aksi kalkulasi, persetujuan, finalisasi, ekspor atau transfer palsu. Status disampaikan lewat teks dan tabel semantik; semua nominal integer IDR sintetis.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Fixture dan batas

Tiga batch: September FINALIZED total17.500.000, settlement berhasil7.400.000; Oktober CALCULATED V2 dengan penghambat absensi belum ditutup/parameter regulasi belum disahkan/nilai negatif; rencana penyesuaian off-cycle Oktober tidak mengubah September. Tinjauan/persetujuan V1 tidak berlaku setelah sumber berubah ke V2. Pratinjau slip hanya untuk batch final, tanggal rilis <= snapshot20Okt, dan penerima yang ada dalam batch. Ini gate fixture, bukan otorisasi pemilik slip produksi. Snapshot September tidak diambil dari master nominal Oktober. Intan tetap termasuk periode September walaupun profil sekarang berakhir.

Enam klaim: EXP-001 hanya kanal payrollOktober; EXP-002 kanal reimbursement dengan berhasil200.000/gagal220.000, nominal asal420.000 tetap utuh; EXP-003 lunas180.000 dengan referensi/tanggal/aktor; EXP-004/005 memiliki bukti/tanggal/nominal serupa dan peringatan tanpa menetapkan kecurangan; EXP-006 terverifikasiFinance belum siapbayar. Klaim ditolak menampilkan bagian belum dibayar sebagai bukan kewajiban yang disetujui. Referensi bukti dan transfer adalah teks sintetis tanpa lampiran/rekening.

Tidak ada tarif hukum, pajak/BPJS, mesin formula/prorata/rounding, rekening, PDF, bank export/API, transaksi settlement, lock final, peran/selfapproval, duplicate prevention atau penyimpanan slip privat operasional. Penyesuaian Intan bukan potongan aset. UI tidak mengubah data HR/keuangan dan belum memenuhi acceptance payroll/expense R3.

## Verifikasi

Gate preview local/testing dengan flag default false tetap berlaku. Input query per register divalidasi, route GET-only, empty/reset tersedia. Tidak ada penggunaan spreadsheet karena modul tidak menghasilkan atau memproses file spreadsheet. Browser inventory masih kosong; build/tests/source review tidak membuktikan visual dan aksesibilitas.
