<?php

namespace App\Support;

use App\Domain\Identity\Access;
use App\Models\User;

class WorkspaceOperations
{
    public static function related(string $key): array
    {
        return match (true) {
            in_array($key, ['import-upload', 'import-map', 'import-preview', 'import-commit', 'import-detail']) => ['import-detail', 'import-map', 'import-preview', 'import-commit'],
            str_starts_with($key, 'export-') => ['export-status', 'export-download'],
            str_starts_with($key, 'payroll-policy') => ['payroll-policy-detail', 'payroll-policy-approve'],
            in_array($key, ['payroll-create', 'payroll-detail', 'payroll-calculate', 'payroll-review', 'payroll-approve', 'payroll-finalize', 'payroll-reset']) => ['payroll-detail', 'payroll-calculate', 'payroll-review', 'payroll-approve', 'payroll-finalize', 'bank-export'],
            str_starts_with($key, 'reimbursement-') => ['reimbursement-detail', 'reimbursement-decide', 'reimbursement-export'],
            $key === 'onboarding-create' || $key === 'case-detail' => ['case-detail', 'case-close'],
            $key === 'asset-create' => ['asset-issue'],
            $key === 'asset-issue' => ['asset-accept', 'asset-return'],
            default => [],
        };
    }

    public static function allowed(User $u, string $gate): bool
    {
        return match ($gate) {
            'any' => true,
            'self' => (bool) $u->employee_id && $u->grants()->where('role', 'employee')->where('scope', 'self')->exists(),
            'hr' => Access::companyPermission($u, 'employees.manage'),
            'hr-admin' => Access::companyPermission($u, 'employees.review'),
            'finance' => Access::companyPermission($u, 'payroll.prepare') || Access::companyPermission($u, 'payroll.approve'),
            'payroll' => Access::companyPermission($u, 'payroll.prepare'),
            'approver' => Access::companyPermission($u, 'payroll.approve'),
            'it' => Access::companyPermission($u, 'assets.manage'),
            'platform' => Access::companyPermission($u, 'accounts.manage'),
            'delegator' => $u->grants()->whereIn('role', ['manager', 'hr_admin', 'hr_officer', 'finance_approver'])->exists(),
            'profile' => self::allowed($u, 'self') || self::allowed($u, 'hr'),
            'reader' => $u->grants()->whereIn('role', Access::EMPLOYEE_READERS)->exists(),
            'hr-reader' => $u->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->exists(),
            'importer' => $u->grants()->whereIn('role', ['hr_admin', 'hr_officer', 'payroll_officer'])->where('scope', 'company')->exists(),
            default => false,
        };
    }

    public static function all(): array
    {
        $f = fn ($name, $label, $type = 'text', $required = true, $extra = []) => OperationFields::field($name, $label, $type, $required, $extra);
        $pick = fn ($name, $label, $source, $required = true) => $f($name, $label, 'lookup', $required, ['source' => $source]);
        $enum = fn ($name, $label, $options, $required = true) => $f($name, $label, 'select', $required, ['options' => $options]);
        $group = fn ($name, $label, $fields, $required = true) => $f($name, $label, 'group', $required, ['fields' => $fields]);
        $repeat = fn ($name, $label, $fields, $max = 40) => $f($name, $label, 'repeat', true, ['fields' => $fields, 'max' => $max]);
        $map = fn ($name, $label, $value) => $f($name, $label, 'map', true, ['fields' => [$f('key', 'Kode tugas / nama kolom'), $value], 'max' => 100]);
        $reason = $f('reason', 'Alasan / dasar tindakan', 'textarea');
        $evidence = $f('evidence', 'Referensi bukti dan hasil pemeriksaan', 'textarea');
        $employee = $pick('employee_id', 'Karyawan', 'people');
        $version = $f('expected_version', 'Versi data yang ditinjau', 'version');
        $empVersion = $f('employee_version', 'Versi karyawan yang ditinjau', 'version');
        $record = fn ($source, $label = 'Catatan yang diproses') => $pick('record_id', $label, $source);
        $period = $pick('employment_period_id', 'Periode kerja', 'employment-periods');
        $placement = [$pick('branch_id', 'Cabang', 'branches'), $pick('department_id', 'Departemen', 'departments'), $pick('job_id', 'Jabatan', 'jobs'), $pick('manager_employee_id', 'Atasan', 'people', false)];
        $owners = $map('owners', 'Penanggung jawab per kode tugas checklist', $pick('value', 'Penanggung jawab', 'reviewers'));
        $financeMoney = fn ($name, $label, $required = true) => $f($name, $label.' (Rp)', 'money', $required);
        $yes = fn ($name, $label) => $f($name, $label, 'boolean');
        $ops = [];
        $add = function ($key, $title, $module, $gate, $endpoint, $fields = [], $method = 'POST', $workflow = null) use (&$ops) {
            $ops[$key] = compact('key', 'title', 'module', 'gate', 'endpoint', 'fields', 'method', 'workflow');
        };
        $workflow = function ($kind, $title, $module, $gate, $fields) use ($add) {
            $add('request-'.$kind, $title, $module, $gate, '/requests', $fields, 'POST', $kind);
        };

        $workflow('swap', 'Ajukan tukar shift', 'people', 'self', [$pick('source_shift_id', 'Shift saya', 'my-shifts'), $pick('target_shift_id', 'Shift rekan dalam cabang dan departemen yang sama', 'swap-shifts'), $reason]);
        $workflow('leave_exception', 'Ajukan pengecualian pemberitahuan cuti', 'people', 'hr', [$employee, $pick('leave_type_id', 'Jenis cuti', 'leave-types'), $f('starts_on', 'Tanggal mulai', 'date'), $f('ends_on', 'Tanggal terakhir (termasuk)', 'date'), $enum('segment', 'Bagian shift', ['full' => 'Sehari penuh', 'first_half' => 'Paruh pertama', 'second_half' => 'Paruh kedua']), $reason, $f('exception_reason', 'Dasar pengecualian HR', 'textarea')]);
        $workflow('leave_cancel', 'Ajukan pembatalan cuti atas nama karyawan', 'people', 'hr', [$employee, $pick('original_request_id', 'Pengajuan cuti disetujui', 'requests'), $reason]);

        foreach (['profile_change' => 'general', 'identity_change' => 'identity', 'bank_change' => 'bank', 'tax_change' => 'tax'] as $kind => $category) {
            $data = match ($category) {
                'general' => [$f('birth_date', 'Tanggal lahir', 'date', false), $f('phone', 'Telepon', 'text', false), $f('address', 'Alamat', 'textarea', false), $group('emergency_contact', 'Kontak darurat', [$f('name', 'Nama'), $f('relationship', 'Hubungan'), $f('phone', 'Telepon')], false), $repeat('education', 'Pendidikan', [$f('institution', 'Institusi'), $f('qualification', 'Kualifikasi'), $f('completed_year', 'Tahun selesai', 'number', false)], 20), $map('custom', 'Field tambahan yang telah didaftarkan', $f('value', 'Nilai'))],
                'identity' => [$f('national_id', 'NIK (16 digit)'), $f('legal_name', 'Nama sesuai identitas')],
                'bank' => [$f('bank_code', 'Kode bank'), $f('account_number', 'Nomor rekening'), $f('account_name', 'Nama pemilik rekening')],
                'tax' => [$f('tax_number', 'Nomor pajak', 'text', false), $f('tax_status', 'Status pajak sesuai kebijakan'), $f('bpjs_health_number', 'Nomor BPJS Kesehatan', 'text', false), $f('bpjs_employment_number', 'Nomor BPJS Ketenagakerjaan', 'text', false)],
            };
            $workflow($kind, ['general' => 'Ajukan perubahan profil', 'identity' => 'Ajukan verifikasi identitas', 'bank' => 'Ajukan verifikasi rekening', 'tax' => 'Ajukan verifikasi pajak/BPJS'][$category], 'people', in_array($category, ['bank', 'tax']) ? 'any' : 'profile', [$employee, $f('category', 'Kategori', 'hidden', true, ['fixed' => $category]), $f('base_version', 'Versi profil saat ini (0 jika belum ada)', 'profile-version'), $group('data', 'Data yang diajukan (menggantikan kategori lengkap)', $data), $reason]);
            $add('profile-'.$category, 'Lihat profil '.['general' => 'umum', 'identity' => 'identitas', 'bank' => 'rekening', 'tax' => 'pajak/BPJS'][$category], 'people', 'any', '/employees/{record}/private-profile/'.$category, [$record('people', 'Karyawan')], 'GET');
        }

        $docFields = [$employee, $empVersion, $period, $pick('document_template_id', 'Template dokumen', 'document-templates')];
        $workflow('contract', 'Susun kontrak / perpanjangan / amendemen', 'administration', 'hr', [...$docFields, $f('contract_type', 'Jenis kontrak'), $f('renewal_sequence', 'Urutan perpanjangan (0 untuk awal)', 'number'), $f('starts_on', 'Mulai berlaku', 'date'), $f('ends_on', 'Batas akhir (tidak termasuk)', 'date'), $pick('amends_id', 'Kontrak primer yang diamendemen', 'contracts', false), $pick('renews_id', 'Kontrak yang diperpanjang', 'contracts', false), $f('terms', 'Ketentuan kontrak', 'textarea'), $reason]);
        $workflow('contract_cancel', 'Batalkan kontrak terjadwal', 'administration', 'hr', [$pick('contract_id', 'Kontrak', 'contracts'), $f('contract_version', 'Versi kontrak', 'version'), $reason]);
        $workflow('letter', 'Ajukan surat / penerbitan ulang', 'administration', 'profile', [...$docFields, $enum('letter_kind', 'Jenis surat', ['employment_certificate' => 'Keterangan kerja', 'service_letter' => 'Surat pengalaman kerja', 'assignment_letter' => 'Surat tugas']), $pick('reissues_id', 'Dokumen yang diterbitkan ulang', 'documents', false), $reason]);
        foreach (['career' => 'Perubahan karier mendatang', 'career_retrospective' => 'Tinjauan karier retrospektif HR–Finance'] as $kind => $title) {
            $workflow($kind, $title, 'administration', 'hr', [$employee, $f('base_version', 'Versi karyawan', 'employee-version'), $f('effective_on', 'Tanggal penempatan mendatang', 'date'), ...($kind === 'career_retrospective' ? [$f('retrospective_on', 'Tanggal riwayat yang ditinjau', 'date')] : []), ...$placement, $reason]);
        }
        $workflow('onboarding', 'Ajukan aktivasi onboarding', 'administration', 'hr', [$pick('lifecycle_case_id', 'Kasus onboarding', 'cases'), $f('case_version', 'Versi kasus', 'version')]);
        $workflow('offboarding', 'Ajukan offboarding', 'administration', 'hr', [$employee, $empVersion, $period, $pick('lifecycle_template_id', 'Template clearance', 'lifecycle-templates'), $f('last_working_on', 'Hari kerja terakhir', 'date'), $f('effective_end_on', 'Tanggal mulai tidak aktif', 'date'), $enum('reason_class', 'Kategori alasan', ['resignation' => 'Mengundurkan diri', 'retirement' => 'Pensiun', 'contract_end' => 'Kontrak berakhir', 'termination' => 'Pemutusan hubungan kerja', 'other' => 'Lainnya']), $reason, $owners]);
        $add('document-template', 'Simpan versi template dokumen', 'administration', 'hr', '/document-templates', [$f('code', 'Kode template'), $enum('kind', 'Jenis', ['contract' => 'Kontrak', 'employment_certificate' => 'Keterangan kerja', 'service_letter' => 'Pengalaman kerja', 'assignment_letter' => 'Surat tugas']), $f('title', 'Judul'), $f('body', 'Isi template; gunakan placeholder yang didukung, misalnya {{employee_name}}', 'textarea')]);
        $roles = ['hr_admin' => 'HR Admin', 'hr_officer' => 'HR Officer', 'manager' => 'Atasan', 'it_asset_officer' => 'Petugas aset', 'finance_approver' => 'Finance'];
        $add('lifecycle-template', 'Simpan template checklist', 'administration', 'hr-admin', '/lifecycle-templates', [$f('code', 'Kode template'), $enum('kind', 'Jenis checklist', ['onboarding' => 'Onboarding', 'offboarding' => 'Offboarding']), $pick('branch_id', 'Cabang khusus (opsional)', 'branches', false), $pick('job_id', 'Jabatan khusus (opsional)', 'jobs', false), $repeat('tasks', 'Tugas checklist', [$f('code', 'Kode tugas'), $f('label', 'Nama tugas'), $enum('role', 'Peran penanggung jawab', $roles), $yes('mandatory', 'Wajib diselesaikan'), $f('due_offset_days', 'Tenggat relatif tanggal efektif (hari; boleh negatif)', 'number'), $f('dependencies', 'Kode tugas prasyarat, satu per baris', 'lines', false)])]);
        $add('onboarding-create', 'Buat onboarding / rehire', 'administration', 'hr', '/onboarding-cases', [$employee, $version, array_replace($period, ['required' => false]), $pick('lifecycle_template_id', 'Template onboarding', 'lifecycle-templates'), $f('starts_on', 'Tanggal mulai', 'date'), ...$placement, $owners]);
        $add('task-complete', 'Selesaikan / kecualikan tugas checklist', 'administration', 'any', '/lifecycle-tasks/{record}', [$record('tasks'), $version, $enum('action', 'Keputusan tugas', ['complete' => 'Selesai', 'waive' => 'Dikecualikan oleh HR']), $evidence]);
        $add('task-reassign', 'Ganti pemilik tugas checklist', 'administration', 'hr', '/lifecycle-tasks/{record}/reassign', [$record('tasks'), $version, $pick('owner_id', 'Pemilik tugas baru', 'reviewers'), $reason]);
        $add('case-close', 'Tutup kasus clearance', 'administration', 'hr-admin', '/lifecycle-cases/{record}/close', [$record('cases'), $version, $yes('allow_open_assets', 'Pengesahan HR untuk kewajiban aset yang masih terbuka'), $reason]);
        $add('contract-recommend', 'Tinjau rekomendasi akhir kontrak', 'administration', 'delegator', '/contract-review-tasks/{record}/recommend', [$record('contract-reviews'), $version, $enum('decision', 'Rekomendasi', ['renew' => 'Perpanjang', 'amend' => 'Amendemen', 'end' => 'Akhiri', 'hr_review' => 'Tinjauan HR']), $reason]);
        $add('contracts', 'Riwayat kontrak karyawan', 'administration', 'any', '/employees/{record}/contracts', [$record('people', 'Karyawan'), $f('as_of', 'Tanggal acuan', 'date', false)], 'GET');
        $add('document-download', 'Unduh dokumen privat', 'administration', 'any', '/documents/{record}/download', [$record('documents')], 'GET');
        $add('case-detail', 'Tinjau checklist dan status kasus', 'administration', 'any', '/lifecycle-cases/{record}', [$record('cases')], 'GET');
        $add('document-preview', 'Pratinjau dokumen sebelum terbit', 'administration', 'any', '/requests/{record}/document-preview', [$record('requests')], 'GET');

        $add('asset-create', 'Daftarkan aset', 'assets', 'it', '/assets', [$f('code', 'Kode aset'), $f('serial', 'Nomor seri', 'text', false), $f('category', 'Kategori'), $pick('branch_id', 'Cabang', 'branches'), $enum('condition', 'Kondisi', ['new' => 'Baru', 'good' => 'Baik', 'worn' => 'Aus', 'damaged' => 'Rusak'])]);
        $add('asset-issue', 'Serahkan aset kepada karyawan', 'assets', 'it', '/assets/{record}/issue', [$record('assets'), $version, $employee, $evidence]);
        $add('asset-accept', 'Konfirmasi penerimaan aset saya', 'assets', 'self', '/asset-custodies/{record}/accept', [$record('custodies'), $version]);
        $add('asset-return', 'Catat pengembalian / kehilangan aset', 'assets', 'it', '/asset-custodies/{record}/return', [$record('custodies'), $version, $enum('condition', 'Kondisi pengembalian', ['good' => 'Baik', 'worn' => 'Aus', 'damaged' => 'Rusak', 'lost' => 'Hilang']), $evidence]);
        $add('consumable', 'Catat barang habis pakai', 'assets', 'it', '/consumable-issues', [$f('item_code', 'Kode barang'), $employee, $f('quantity', 'Jumlah', 'number'), $evidence]);
        $add('asset-settlement', 'Selesaikan kewajiban aset secara terpisah', 'assets', 'approver', '/asset-settlements/{record}/resolve', [$record('settlements'), $version, $enum('resolution', 'Penyelesaian', ['no_charge' => 'Tanpa tagihan', 'separate_agreement' => 'Kesepakatan terpisah']), $evidence]);
        $add('assets-list', 'Daftar aset perusahaan', 'assets', 'it', '/assets', [], 'GET');
        $add('assets-mine', 'Aset yang menjadi tanggung jawab saya', 'assets', 'self', '/my-assets', [], 'GET');

        $workflow('compensation', 'Ajukan versi kompensasi', 'finance', 'payroll', [$employee, $f('valid_from', 'Tanggal efektif', 'date'), $financeMoney('monthly_base_minor', 'Gaji pokok bulanan'), $pick('base_compensation_id', 'Versi kompensasi sebelumnya', 'compensations', false), $reason]);
        $workflow('opening_payroll', 'Ajukan saldo awal payroll / YTD', 'finance', 'payroll', [$employee, $f('tax_year', 'Tahun pajak', 'number'), $f('through_on', 'Riwayat sampai tanggal (termasuk)', 'date'), $f('source_key', 'Referensi sumber unik'), $financeMoney('gross_minor', 'Bruto sebelumnya'), $financeMoney('tax_paid_minor', 'Pajak dipotong sebelumnya'), $reason]);
        $workflow('expense', 'Ajukan reimbursement', 'finance', 'self', [$pick('expense_policy_id', 'Kategori/kebijakan klaim', 'expense-policies'), $f('incurred_on', 'Tanggal pengeluaran', 'date'), $financeMoney('amount_minor', 'Nominal klaim'), $pick('cost_center_id', 'Departemen biaya saat pengeluaran', 'departments'), $f('description', 'Keperluan pengeluaran', 'textarea')]);
        $add('expense-policy', 'Konfigurasi kebijakan reimbursement', 'finance', 'approver', '/expense-policies', [$f('category', 'Kode kategori'), $f('valid_from', 'Mulai berlaku', 'date'), $f('valid_until', 'Batas akhir tidak termasuk', 'date'), $financeMoney('max_claim_minor', 'Batas per klaim'), $financeMoney('max_month_minor', 'Batas bulanan'), $f('minimum_tenure_days', 'Masa kerja minimum (hari)', 'number'), $f('job_ids', 'Jabatan yang memenuhi syarat (kosong berarti semua)', 'multi', false, ['source' => 'jobs'])]);
        $add('claim-detail', 'Tinjau bukti dan nominal klaim', 'finance', 'any', '/expense-claims/{record}', [$record('claims')], 'GET');
        $add('claim-allocate', 'Pilih kanal pembayaran klaim', 'finance', 'approver', '/expense-claims/{record}/allocate', [$record('claims'), $enum('channel', 'Kanal', ['payroll' => 'Payroll', 'reimbursement' => 'Pembayaran terpisah']), $pick('payroll_batch_id', 'Batch payroll jika kanal payroll', 'payroll-batches', false)]);
        $outcome = [$f('external_event_id', 'Referensi unik peristiwa bank'), $enum('status', 'Hasil pembayaran', ['paid' => 'Dibayar', 'failed' => 'Gagal']), $financeMoney('amount_minor', 'Nominal dibayar (0 jika gagal)'), $f('reference', 'Referensi bank'), $f('paid_on', 'Tanggal pembayaran', 'date')];
        $add('claim-settle', 'Catat pembayaran klaim terpisah', 'finance', 'approver', '/expense-allocations/{record}/settle', [$record('allocations'), ...array_filter($outcome, fn ($field) => $field['name'] !== 'status')]);
        $add('reimbursement-create', 'Buat batch reimbursement', 'finance', 'approver', '/reimbursement-batches', [$f('allocation_ids', 'Klaim teralokasi yang dibayar', 'multi', true, ['source' => 'allocations']), $reason]);
        $add('reimbursement-detail', 'Tinjau batch reimbursement dan instruksi bank', 'finance', 'approver', '/reimbursement-batches/{record}', [$record('reimbursements')], 'GET');
        $add('reimbursement-decide', 'Sahkan / segarkan / batalkan batch reimbursement', 'finance', 'approver', '/reimbursement-batches/{record}/decide', [$record('reimbursements'), $version, $enum('action', 'Keputusan', ['approve' => 'Sahkan independen', 'refresh' => 'Segarkan sumber dan batalkan approval lama', 'cancel' => 'Batalkan sisa batch']), $evidence]);
        $add('reimbursement-export', 'Buat instruksi bank / retry sisa belum dibayar', 'finance', 'approver', '/reimbursement-batches/{record}/bank-export', [$record('reimbursements'), $pick('template_id', 'Template bank yang disahkan', 'bank-templates'), $reason]);
        $add('reimbursement-download', 'Unduh instruksi reimbursement privat', 'finance', 'approver', '/reimbursement-instructions/{record}/download', [$record('instructions'), $reason]);
        $add('reimbursement-outcome', 'Rekonsiliasi item instruksi reimbursement', 'finance', 'approver', '/reimbursement-instruction-items/{record}/outcomes', [$record('instruction-items'), ...$outcome]);

        $component = [$f('code', 'Kode komponen'), $f('label', 'Nama komponen'), $enum('kind', 'Jenis', ['earning' => 'Pendapatan', 'deduction' => 'Potongan']), $enum('method', 'Metode', ['fixed' => 'Nominal tetap', 'ratio' => 'Rasio', 'brackets' => 'Lapisan progresif']), $enum('basis', 'Dasar', ['base' => 'Gaji pokok', 'gross' => 'Bruto', 'overtime_minutes' => 'Menit lembur', 'late_minutes' => 'Menit terlambat', 'unpaid_leave_minutes' => 'Menit cuti tanpa upah', 'absent_shifts' => 'Shift tanpa kehadiran', 'prior_gross' => 'Bruto YTD sebelumnya', 'prior_tax_paid' => 'Potongan pajak YTD', 'annual_gross' => 'Bruto tahunan kumulatif']), $financeMoney('amount_minor', 'Nominal tetap', false), $f('numerator', 'Pembilang rasio', 'number', false), $f('denominator', 'Penyebut rasio', 'number', false), $financeMoney('cap_minor', 'Batas nominal', false), $f('tax_statuses', 'Status pajak yang berlaku, satu per baris', 'lines', false), $f('tax_component', 'Komponen potongan pajak', 'boolean', false), $f('subtract_prior_tax', 'Kurangi potongan pajak YTD sebelumnya', 'boolean', false), $repeat('brackets', 'Lapisan progresif sesuai kebijakan', [$financeMoney('up_to', 'Batas lapisan (kosong untuk tanpa batas)', false), $f('numerator', 'Pembilang', 'number'), $f('denominator', 'Penyebut', 'number')], 30)];
        $add('payroll-policy', 'Susun kebijakan payroll draft', 'finance', 'payroll', '/payroll-policies', [$f('code', 'Kode kebijakan'), $f('valid_from', 'Mulai berlaku', 'date'), $f('valid_until', 'Batas akhir tidak termasuk', 'date'), $f('official_sources', 'Tautan HTTPS sumber resmi, satu per baris', 'lines'), $group('rules', 'Aturan yang harus disahkan ahli payroll', [$enum('rounding', 'Pembulatan', ['half_up' => 'Setengah ke atas', 'floor' => 'Ke bawah', 'ceiling' => 'Ke atas']), $enum('proration_basis', 'Dasar prorata', ['calendar_days' => 'Hari kalender']), $yes('requires_tax_profile', 'Wajib profil pajak terverifikasi'), $f('applicability_statement', 'Pernyataan ruang berlaku kebijakan', 'textarea'), $f('variation_warning_percent', 'Ambang peringatan perubahan neto (%)', 'number'), $repeat('components', 'Komponen yang telah ditetapkan', $component)])]);
        $add('payroll-policy-detail', 'Tinjau kebijakan payroll', 'finance', 'finance', '/payroll-policies/{record}', [$record('payroll-policies')], 'GET');
        $add('payroll-policy-approve', 'Sahkan kebijakan payroll secara independen', 'finance', 'approver', '/payroll-policies/{record}/approve', [$record('payroll-policies'), $evidence]);
        $add('payroll-create', 'Buat batch payroll', 'finance', 'payroll', '/payroll-batches', [$pick('attendance_period_id', 'Periode kehadiran tertutup', 'attendance-periods'), $pick('payroll_policy_id', 'Kebijakan yang disahkan', 'payroll-policies'), $enum('kind', 'Jenis batch', ['regular' => 'Reguler', 'off_cycle' => 'Koreksi off-cycle'])]);
        $add('payroll-detail', 'Tinjau sumber, perhitungan dan slip payroll', 'finance', 'finance', '/payroll-batches/{record}', [$record('payroll-batches')], 'GET');
        foreach (['calculate' => 'Hitung payroll', 'review' => 'Tinjau hasil payroll', 'approve' => 'Sahkan batch payroll', 'finalize' => 'Finalisasi payroll dan jadwal rilis slip', 'reset' => 'Kembalikan payroll ke draf'] as $action => $title) {
            $fields = [$record('payroll-batches'), $version];
            if ($action === 'review') {
                $fields[] = $f('acknowledge_warnings', 'Saya telah memeriksa peringatan perubahan nominal', 'boolean', false);
                $fields[] = $f('reason', 'Dasar tinjauan peringatan (jika ada)', 'textarea', false);
            } elseif ($action === 'finalize') {
                $fields[] = $f('release_at', 'Waktu rilis slip', 'instant');
            } elseif ($action === 'reset') {
                $fields[] = $reason;
            }
            $add('payroll-'.$action, $title, 'finance', in_array($action, ['approve', 'finalize']) ? 'approver' : 'payroll', '/payroll-batches/{record}/'.$action, $fields);
        }
        $add('payroll-history', 'Tinjau opening dan konteks YTD karyawan', 'finance', 'finance', '/employees/{record}/payroll-history', [$record('people', 'Karyawan'), $f('tax_year', 'Tahun pajak', 'number'), $f('before_on', 'Sebelum tanggal', 'date')], 'GET');
        $add('payroll-adjustment', 'Ajukan koreksi payroll periode tertutup', 'finance', 'payroll', '/payroll-adjustments', [$f('source_key', 'Referensi koreksi unik'), $pick('source_payroll_row_id', 'Baris payroll final sumber', 'payroll-rows'), $pick('attendance_adjustment_id', 'Penyesuaian kehadiran terkait', 'attendance-adjustments', false), $pick('payroll_batch_id', 'Batch tujuan draf', 'payroll-batches'), $financeMoney('amount_minor', 'Koreksi bertanda positif/negatif'), $reason]);
        $add('payroll-adjustment-detail', 'Tinjau koreksi payroll privat', 'finance', 'finance', '/payroll-adjustments/{record}', [$record('adjustments')], 'GET');
        $add('payroll-adjustment-decide', 'Putuskan koreksi payroll', 'finance', 'approver', '/payroll-adjustments/{record}/decide', [$record('adjustments'), $version, $enum('decision', 'Keputusan', ['approve' => 'Setujui', 'reject' => 'Tolak', 'cancel' => 'Batalkan']), $evidence]);
        $add('payslip', 'Unduh slip gaji privat', 'finance', 'any', '/payslips/{record}/download', [$record('payslips')], 'GET');
        $bankFields = ['batch_id' => 'Referensi batch', 'payment_id' => 'Referensi unik pembayaran klaim', 'employee_number' => 'Nomor karyawan', 'bank_code' => 'Kode bank', 'account_number' => 'Nomor rekening', 'account_name' => 'Nama rekening', 'amount_idr' => 'Nominal rupiah'];
        $add('bank-template', 'Susun format ekspor bank', 'finance', 'payroll', '/bank-export-templates', [$f('code', 'Kode format'), $repeat('columns', 'Urutan kolom sesuai spesifikasi bank', [$f('label', 'Nama kolom bank'), $enum('field', 'Sumber kolom', $bankFields)], 12)]);
        $add('bank-template-confirm', 'Konfirmasi format bank yang telah diperiksa', 'finance', 'approver', '/bank-export-templates/{record}/confirm', [$record('bank-templates')]);
        $add('bank-export', 'Buat ekspor pembayaran payroll', 'finance', 'approver', '/payroll-batches/{record}/bank-export', [$record('payroll-batches'), $pick('template_id', 'Format bank terkonfirmasi', 'bank-templates'), $reason]);
        $add('bank-download', 'Unduh ekspor payroll privat', 'finance', 'approver', '/bank-exports/{record}/download', [$record('bank-exports'), $reason]);
        $add('payroll-outcome', 'Rekonsiliasi hasil pembayaran payroll', 'finance', 'approver', '/payroll-rows/{record}/payment-outcomes', [$record('payroll-rows'), ...$outcome]);

        $importKinds = ['employees' => 'Karyawan', 'branches' => 'Cabang', 'departments' => 'Departemen', 'jobs' => 'Jabatan', 'rosters' => 'Roster draf', 'leave_credits' => 'Saldo awal cuti', 'compensation' => 'Kompensasi draf', 'opening_payroll' => 'Opening payroll draf'];
        $add('import-upload', 'Unggah impor CSV/XLSX', 'data', 'any', '/imports', [$enum('kind', 'Jenis impor', $importKinds), $f('file', 'Berkas CSV/XLSX maksimal 10 MB', 'file')]);
        $add('import-detail', 'Tinjau staging, kesalahan dan hasil impor', 'data', 'any', '/imports/{record}', [$record('imports')], 'GET');
        $add('import-map', 'Petakan kolom impor', 'data', 'any', '/imports/{record}/map', [$record('imports'), $version, $map('mapping', 'Kolom tujuan → nama kolom sumber', $f('value', 'Nama header sumber'))]);
        $add('import-preview', 'Validasi dan pratinjau impor', 'data', 'any', '/imports/{record}/preview', [$record('imports'), $version]);
        $add('import-commit', 'Terapkan impor yang telah ditinjau', 'data', 'any', '/imports/{record}/commit', [$record('imports'), $version, $f('checksum', 'Checksum hasil pratinjau', 'checksum'), $yes('background', 'Proses melalui antrean latar belakang')]);
        foreach ($importKinds as $kind => $title) {
            $add('import-template-'.$kind, 'Unduh template '.$title, 'data', 'any', '/import-templates/'.$kind, [], 'GET');
        }
        $reportKinds = ['attendance' => 'Kehadiran', 'overtime' => 'Lembur', 'leave_balances' => 'Saldo cuti', 'contracts' => 'Kontrak', 'lifecycle' => 'Lifecycle', 'assets' => 'Aset', 'payroll' => 'Payroll', 'expenses' => 'Klaim'];
        $filters = [$f('starts_on', 'Tanggal mulai', 'date'), $f('ends_on', 'Batas akhir tidak termasuk', 'date'), $pick('branch_id', 'Cabang (opsional)', 'branches', false), $pick('department_id', 'Departemen (opsional)', 'departments', false), $pick('employee_id', 'Karyawan (opsional)', 'people', false), $enum('employment_status', 'Status kerja (opsional)', ['PREBOARDING' => 'Pra-bergabung', 'ACTIVE' => 'Aktif', 'OFFBOARDING' => 'Offboarding', 'ENDED' => 'Berakhir'], false)];
        foreach ($reportKinds as $kind => $title) {
            $gate = $kind === 'assets' ? 'it' : ($kind === 'expenses' ? 'approver' : ($kind === 'payroll' ? 'finance' : (in_array($kind, ['contracts', 'lifecycle']) ? 'hr' : 'reader')));
            $add('report-'.$kind, 'Laporan '.$title, 'data', $gate, '/reports/'.$kind, $filters, 'GET');
            $add('export-'.$kind, 'Ekspor laporan '.$title, 'data', $gate, '/reports/'.$kind.'/exports', [...$filters, $enum('format', 'Format', ['csv' => 'CSV', 'xlsx' => 'Excel XLSX']), $reason]);
        }
        $add('export-status', 'Status ekspor laporan', 'data', 'any', '/report-exports/{record}', [$record('exports')], 'GET');
        $add('export-download', 'Unduh ekspor laporan privat', 'data', 'any', '/report-exports/{record}/download', [$record('exports')], 'GET');

        $add('notification-preferences', 'Preferensi notifikasi', 'settings', 'any', '/notification-preferences', [], 'GET');
        $add('notification-update', 'Simpan preferensi notifikasi', 'settings', 'any', '/notification-preferences', [$yes('email_enabled', 'Aktifkan email'), $yes('in_app_enabled', 'Aktifkan notifikasi aplikasi'), $enum('timezone', 'Zona waktu', ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT']), $enum('delivery_mode', 'Pengiriman', ['immediate' => 'Segera', 'daily' => 'Ringkasan harian']), $f('digest_time', 'Jam ringkasan harian', 'time'), $f('quiet_start', 'Mulai waktu tenang', 'time', false), $f('quiet_end', 'Akhir waktu tenang', 'time', false)]);
        $delegationRoles = ['manager' => 'Atasan', 'hr_admin' => 'HR Admin', 'hr_officer' => 'HR Officer', 'finance_approver' => 'Finance'];
        $add('delegate', 'Delegasikan persetujuan dalam scope saya', 'settings', 'delegator', '/workflow-delegations', [$pick('delegate_id', 'Penerima delegasi aktif dengan mandat/MFA', 'reviewers'), $enum('role', 'Peran yang didelegasikan', $delegationRoles), $f('starts_at', 'Mulai delegasi', 'instant'), $f('ends_at', 'Akhir delegasi', 'instant'), $f('employee_ids', 'Subjek karyawan eksplisit', 'multi', true, ['source' => 'people']), $reason]);
        $add('delegate-revoke', 'Cabut delegasi saya', 'settings', 'delegator', '/workflow-delegations/{record}/revoke', [$record('delegations')]);
        $add('workflow-reassign', 'Tetapkan ulang peninjau berwenang', 'settings', 'delegator', '/requests/{record}/reassign', [$record('requests'), $version, $pick('replacement_id', 'Peninjau baru', 'reviewers'), $reason]);
        $workflowKinds = array_combine(['leave', 'leave_exception', 'leave_cancel', 'correction', 'overtime', 'swap', 'contract', 'contract_cancel', 'letter', 'career', 'career_retrospective', 'onboarding', 'offboarding', 'compensation', 'opening_payroll', 'expense', 'profile_change', 'identity_change', 'bank_change', 'tax_change'], ['Cuti', 'Pengecualian cuti HR', 'Pembatalan cuti', 'Koreksi kehadiran', 'Lembur', 'Tukar shift', 'Kontrak', 'Pembatalan kontrak', 'Surat', 'Karier', 'Karier retrospektif', 'Aktivasi onboarding', 'Offboarding', 'Kompensasi', 'Opening payroll', 'Klaim', 'Profil', 'Identitas', 'Rekening', 'Pajak']);
        $add('bulk-decisions', 'Putuskan beberapa pengajuan yang telah diperiksa', 'settings', 'delegator', '/requests/bulk-decisions', [$repeat('items', 'Pengajuan dan versi yang ditinjau', [$pick('id', 'Pengajuan', 'requests'), $version], 100), $enum('decision', 'Keputusan', ['approve' => 'Setujui', 'reject' => 'Tolak']), $reason]);
        $add('workflow-definition', 'Konfigurasi alur persetujuan', 'settings', 'hr-admin', '/workflow-definitions', [$f('name', 'Nama alur'), $enum('kind', 'Jenis pengajuan', $workflowKinds), $pick('branch_id', 'Cabang khusus', 'branches', false), $pick('department_id', 'Departemen khusus', 'departments', false), $f('min_amount_minor', 'Batas nominal minimum (sen)', 'number'), $f('max_amount_minor', 'Batas nominal maksimum (sen)', 'number', false), $f('priority', 'Prioritas pencocokan (0–100)', 'number'), $repeat('steps', 'Urutan peninjauan', [$enum('role', 'Peran peninjau', $delegationRoles + ['counterparty' => 'Rekan penukar shift'])], 8), $group('deadline_calendar', 'Kalender tenggat kerja (opsional; isi lengkap jika dipakai)', [$f('timezone', 'Zona waktu IANA'), $pick('branch_id', 'Cabang kalender', 'branches'), $f('working_days', 'Hari kerja: 1=Senin hingga 7=Minggu, satu per baris', 'lines'), $f('start_local', 'Mulai jam kerja', 'time'), $f('end_local', 'Akhir jam kerja', 'time'), $f('due_minutes', 'Tenggat peninjauan (menit kerja)', 'number'), $f('escalate_minutes', 'Tenggat eskalasi (menit kerja)', 'number')], false)]);
        $add('profile-field', 'Daftarkan field profil tambahan', 'settings', 'hr-admin', '/profile-fields', [$f('code', 'Kode field'), $f('label', 'Label'), $enum('type', 'Tipe nilai', ['text' => 'Teks', 'integer' => 'Bilangan', 'date' => 'Tanggal', 'boolean' => 'Ya/Tidak', 'select' => 'Pilihan']), $f('options', 'Pilihan jika tipe pilihan, satu per baris', 'lines', false)]);
        $add('account-access', 'Ubah status akun dan mandat peran', 'settings', 'platform', '/accounts/{record}/access', [$record('accounts', 'Akun'), $version, $yes('is_active', 'Akun aktif'), $repeat('grants', 'Mandat peran lengkap (menggantikan daftar lama)', [$enum('role', 'Peran', array_combine(Access::ROLES, Access::ROLES)), $enum('scope', 'Scope', ['company' => 'Perusahaan', 'branch' => 'Cabang', 'department' => 'Departemen', 'team' => 'Tim', 'self' => 'Diri sendiri']), $pick('branch_id', 'Cabang untuk scope cabang', 'branches', false), $pick('department_id', 'Departemen untuk scope departemen', 'departments', false)], 30)]);
        $add('account-suspend', 'Jadwalkan pembekuan akun', 'settings', 'platform', '/accounts/{record}/suspensions', [$record('accounts', 'Akun'), $version, $f('starts_at', 'Mulai pembekuan', 'instant'), $f('ends_at', 'Akhir pembekuan (opsional)', 'instant', false), $reason]);
        $add('suspension-revoke', 'Cabut pembekuan akun', 'settings', 'platform', '/account-suspensions/{record}/revoke', [$record('suspensions'), $reason]);
        $add('operations-health', 'Kesehatan antrean notifikasi', 'settings', 'platform', '/operations/health', [], 'GET');
        $add('delivery-retry', 'Jadwalkan ulang pengiriman gagal', 'settings', 'platform', '/operations/deliveries/{record}/retry', [$record('deliveries'), $reason]);
        $add('staffing', 'Konfigurasi minimum staffing', 'settings', 'hr-admin', '/staffing-policies', [$f('code', 'Kode kebijakan'), $pick('work_location_id', 'Lokasi kerja', 'work-locations'), $pick('job_id', 'Jabatan tertentu', 'jobs', false), $f('valid_from', 'Mulai berlaku', 'date'), $f('valid_until', 'Batas akhir tidak termasuk', 'date'), $f('working_days', 'Hari kerja 1–7, satu per baris', 'lines'), $f('start_local', 'Mulai cakupan', 'time'), $f('end_local', 'Akhir cakupan', 'time'), $enum('end_day_offset', 'Hari akhir', ['0' => 'Hari sama', '1' => 'Hari berikutnya']), $f('minimum_staff', 'Jumlah minimum staf', 'number'), $yes('is_blocking', 'Wajib dipenuhi sebelum publikasi')]);

        return $ops;
    }
}
