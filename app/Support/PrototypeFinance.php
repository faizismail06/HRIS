<?php

namespace App\Support;

/** Synthetic IDR examples only. No tax rates, bank data, payroll engine or payment API. */
class PrototypeFinance
{
    public static function idr(int $amount): string
    {
        return ($amount < 0 ? '−' : '').'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    public static function payrollStates(): array
    {
        return ['DRAFT' => 'Draf', 'CALCULATED' => 'Dihitung', 'REVIEWED' => 'Ditinjau', 'APPROVED' => 'Disetujui', 'FINALIZED' => 'Final'];
    }

    public static function claimStates(): array
    {
        return ['SUBMITTED' => 'Diajukan', 'MANAGER_APPROVED' => 'Disetujui atasan', 'FINANCE_VERIFIED' => 'Diverifikasi Finance', 'APPROVED_FOR_PAYMENT' => 'Siap dibayar', 'PAID' => 'Lunas', 'REJECTED' => 'Ditolak', 'WITHDRAWN' => 'Ditarik'];
    }

    public static function payrolls(): array
    {
        $rows = [
            ['id' => 'PAY-SEP-26', 'period' => '2026-09', 'label' => 'September 2026', 'status' => 'FINALIZED', 'version' => 'SNAP-SEP-V1', 'rules' => 'DEMO-IDR-V1 · tanpa perhitungan regulasi', 'prepared' => 'Petugas payroll contoh', 'approved' => 'Lestari Anindya · Approver contoh', 'release' => '2026-10-01', 'note' => 'Snapshot final tidak mengikuti perubahan master Oktober. Angka hanya ilustrasi, bukan perhitungan gaji atau ketentuan pajak/BPJS.',
                'checks' => [['level' => 'INFO', 'title' => 'Snapshot contoh dikunci', 'detail' => 'Sumber periode dan versi komponen disimpan di fixture. Koreksi setelah final memakai penyesuaian terpisah.']],
                'employees' => [
                    self::payee('EMP-004', 'Nadia Pramesti', 'Koordinator Operasional', 'Jakarta', [['Gaji dasar contoh', 6000000, 'COMP-004-SEP · nilai bulanan contoh'], ['Tunjangan contoh', 500000, 'COMP-004-SEP · nominal tetap'], ['Penyesuaian contoh', -100000, 'ADJ-DEMO-004 · koreksi periode sebelumnya']], [['id' => 'SET-PAY-001', 'amount' => 6400000, 'state' => 'CLEARED', 'reference' => 'TRF-DEMO-SEP-001', 'date' => '2026-10-01', 'actor' => 'Petugas Finance contoh']]),
                    self::payee('EMP-005', 'Arif Wicaksono', 'Analis Keuangan', 'Bandung', [['Gaji dasar contoh', 7000000, 'COMP-005-SEP · nilai bulanan contoh'], ['Tunjangan contoh', 300000, 'COMP-005-SEP · nominal tetap']], [['id' => 'SET-PAY-002', 'amount' => 7300000, 'state' => 'FAILED', 'reference' => 'TRF-DEMO-SEP-002', 'date' => '2026-10-01', 'actor' => 'Petugas Finance contoh']]),
                    self::payee('EMP-011', 'Intan Kusuma', 'Staf Operasional', 'Bandung', [['Gaji dasar contoh', 4000000, 'COMP-011-SEP · aktif sampai 30 September'], ['Penyesuaian contoh', -200000, 'ADJ-DEMO-011 · nominal contoh, bukan tagihan aset']], [['id' => 'SET-PAY-003', 'amount' => 1000000, 'state' => 'CLEARED', 'reference' => 'TRF-DEMO-SEP-003', 'date' => '2026-10-02', 'actor' => 'Petugas Finance contoh']]),
                ]],
            ['id' => 'PAY-OCT-26', 'period' => '2026-10', 'label' => 'Oktober 2026', 'status' => 'CALCULATED', 'version' => 'SNAP-OCT-V2', 'rules' => 'DEMO-IDR-V2 · tanpa perhitungan regulasi', 'prepared' => 'Petugas payroll contoh', 'approved' => null, 'release' => null, 'note' => 'Sumber berubah setelah tinjauan V1; tinjauan dan persetujuan lama tidak berlaku untuk V2. Masih ada blocker; belum final dan slip belum dirilis.',
                'checks' => [
                    ['level' => 'BLOCKER', 'title' => 'Periode kehadiran belum ditutup', 'detail' => 'Rekap Oktober masih berjalan; tidak dapat dipakai sebagai snapshot final.'],
                    ['level' => 'BLOCKER', 'title' => 'Parameter regulasi belum disahkan', 'detail' => 'Tidak ada tarif pajak/BPJS operasional pada prototipe ini.'],
                    ['level' => 'BLOCKER', 'title' => 'Nilai bersih negatif', 'detail' => 'Contoh Fajar memerlukan koreksi sumber sebelum finalisasi.'],
                    ['level' => 'WARNING', 'title' => 'Variasi nominal Nadia perlu ditinjau', 'detail' => 'Nominal dan klaim berbeda dari bulan lalu; periksa sumber per komponen.'],
                ],
                'employees' => [
                    self::payee('EMP-004', 'Nadia Pramesti', 'Koordinator Operasional', 'Jakarta', [['Gaji dasar contoh', 6500000, 'COMP-004-OCT · versi nominal Oktober'], ['Tunjangan contoh', 500000, 'COMP-004-OCT · nominal tetap'], ['Reimbursement EXP-001', 350000, 'EXP-001 · hanya kanal payroll PAY-OCT-26']], []),
                    self::payee('EMP-010', 'Fajar Nugraha', 'HR Officer', 'Jakarta', [['Gaji dasar contoh', 4000000, 'COMP-010-OCT · nilai bulanan contoh'], ['Penyesuaian salah contoh', -4200000, 'ADJ-DEMO-010 · sengaja menjadi blocker, belum disetujui']], []),
                ]],
            ['id' => 'PAY-ADJ-26', 'period' => '2026-10', 'label' => 'Rencana penyesuaian Oktober', 'status' => 'DRAFT', 'version' => 'SNAP-ADJ-V1', 'rules' => 'DEMO-IDR-V1 · rencana off-cycle', 'prepared' => 'Petugas payroll contoh', 'approved' => null, 'release' => null, 'note' => 'Koreksi setelah periode final dibuat sebagai rencana penyesuaian terpisah, tidak mengubah batch September.', 'checks' => [['level' => 'BLOCKER', 'title' => 'Belum dihitung dan ditinjau', 'detail' => 'Nominal masih rancangan; bukan pembayaran yang disetujui.']], 'employees' => [self::payee('EMP-011', 'Intan Kusuma', 'Staf Operasional', 'Bandung', [['Rencana koreksi periode lalu', 150000, 'ADJ-DEMO-011-NEXT · referensi PAY-SEP-26']], [])]],
        ];

        return array_map(function ($row) {
            $row['total'] = array_sum(array_column($row['employees'], 'net'));
            $row['paid'] = array_sum(array_column($row['employees'], 'paid'));
            $row['payment_label'] = $row['paid'] === 0 ? 'Belum dibayar' : ($row['paid'] === $row['total'] ? 'Lunas' : 'Sebagian dibayar');
            $row['status_label'] = self::payrollStates()[$row['status']];

            return $row;
        }, $rows);
    }

    private static function payee(string $id, string $name, string $job, string $branch, array $components, array $settlements): array
    {
        $net = array_sum(array_column($components, 1));
        $paid = self::cleared($settlements);
        $payment_label = $paid === $net ? 'Lunas' : ($paid > 0 ? 'Sebagian dibayar' : (collect($settlements)->contains('state', 'FAILED') ? 'Transfer gagal' : 'Belum dibayar'));

        return compact('id', 'name', 'job', 'branch', 'components', 'settlements', 'net', 'paid', 'payment_label');
    }

    public static function cleared(array $settlements): int
    {
        return array_sum(array_column(array_filter($settlements, fn ($settlement) => $settlement['state'] === 'CLEARED'), 'amount'));
    }

    public static function payroll(string $id): ?array
    {
        return collect(self::payrolls())->firstWhere('id', $id);
    }

    public static function released(array $batch): bool
    {
        return $batch['status'] === 'FINALIZED' && $batch['release'] !== null && $batch['release'] <= '2026-10-20';
    }

    public static function claims(): array
    {
        $rows = [
            ['id' => 'EXP-001', 'employee' => 'EMP-004', 'title' => 'Perjalanan kunjungan cabang', 'date' => '2026-10-12', 'category' => 'Transportasi', 'cost_center' => 'CC-OPS-JKT', 'amount' => 350000, 'status' => 'APPROVED_FOR_PAYMENT', 'evidence' => 'EVD-DEMO-EXP001', 'channel' => 'PAYROLL', 'batch' => 'PAY-OCT-26', 'duplicate' => null, 'note' => 'Sudah masuk rencana payroll Oktober. Tidak boleh dimasukkan lagi ke batch reimbursement terpisah.', 'settlements' => []],
            ['id' => 'EXP-002', 'employee' => 'EMP-005', 'title' => 'Transportasi rapat lintas unit', 'date' => '2026-10-08', 'category' => 'Transportasi', 'cost_center' => 'CC-FIN-BDG', 'amount' => 420000, 'status' => 'APPROVED_FOR_PAYMENT', 'evidence' => 'EVD-DEMO-EXP002', 'channel' => 'REIMBURSEMENT', 'batch' => 'RMB-OCT-01', 'duplicate' => null, 'note' => 'Pembayaran sebagian dicatat sebagai settlement. Klaim tetap Rp 420.000, bukan diubah menjadi sisa tagihan.', 'settlements' => [['id' => 'SET-EXP-002A', 'amount' => 200000, 'state' => 'CLEARED', 'reference' => 'TRF-DEMO-EXP-002A', 'date' => '2026-10-15', 'actor' => 'Ayu Pertiwi · Finance'], ['id' => 'SET-EXP-002B', 'amount' => 220000, 'state' => 'FAILED', 'reference' => 'TRF-DEMO-EXP-002B', 'date' => '2026-10-16', 'actor' => 'Ayu Pertiwi · Finance']]],
            ['id' => 'EXP-003', 'employee' => 'EMP-007', 'title' => 'Kebutuhan operasional shift', 'date' => '2026-10-05', 'category' => 'Perlengkapan', 'cost_center' => 'CC-OPS-SBY', 'amount' => 180000, 'status' => 'PAID', 'evidence' => 'EVD-DEMO-EXP003', 'channel' => 'REIMBURSEMENT', 'batch' => 'RMB-OCT-01', 'duplicate' => null, 'note' => 'Settlement sudah penuh; klaim ini tidak dapat masuk kanal pembayaran lain.', 'settlements' => [['id' => 'SET-EXP-003', 'amount' => 180000, 'state' => 'CLEARED', 'reference' => 'TRF-DEMO-EXP-003', 'date' => '2026-10-14', 'actor' => 'Ayu Pertiwi · Finance']]],
            ['id' => 'EXP-004', 'employee' => 'EMP-008', 'title' => 'Transportasi kegiatan tim', 'date' => '2026-10-13', 'category' => 'Transportasi', 'cost_center' => 'CC-MKT-BDG', 'amount' => 90000, 'status' => 'SUBMITTED', 'evidence' => 'EVD-DEMO-SAME-004', 'channel' => null, 'batch' => null, 'duplicate' => 'EXP-005', 'note' => 'Bukti, nominal dan tanggal sama dengan pengajuan terdahulu. Peringatan perlu ditinjau; tidak otomatis menetapkan kecurangan.', 'settlements' => []],
            ['id' => 'EXP-005', 'employee' => 'EMP-008', 'title' => 'Arsip transportasi ditolak', 'date' => '2026-10-13', 'category' => 'Transportasi', 'cost_center' => 'CC-MKT-BDG', 'amount' => 90000, 'status' => 'REJECTED', 'evidence' => 'EVD-DEMO-SAME-004', 'channel' => null, 'batch' => null, 'duplicate' => 'EXP-004', 'note' => 'Contoh keputusan: keterangan pengeluaran belum memadai. Tidak ada pembayaran atau settlement.', 'settlements' => []],
            ['id' => 'EXP-006', 'employee' => 'EMP-010', 'title' => 'Bahan orientasi karyawan', 'date' => '2026-10-16', 'category' => 'Perlengkapan', 'cost_center' => 'CC-HR-JKT', 'amount' => 125000, 'status' => 'FINANCE_VERIFIED', 'evidence' => 'EVD-DEMO-EXP006', 'channel' => null, 'batch' => null, 'duplicate' => null, 'note' => 'Verifikasi Finance belum berarti disetujui untuk pembayaran. Kanal belum ditetapkan.', 'settlements' => []],
        ];

        return array_map(fn ($row) => $row + ['person' => PrototypePeople::find($row['employee']), 'status_label' => self::claimStates()[$row['status']], 'paid' => self::cleared($row['settlements']), 'remaining' => $row['amount'] - self::cleared($row['settlements'])], $rows);
    }

    public static function claim(string $id): ?array
    {
        return collect(self::claims())->firstWhere('id', $id);
    }
}
