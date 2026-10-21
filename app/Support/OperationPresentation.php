<?php

namespace App\Support;

class OperationPresentation
{
    public static function money(string $key): bool
    {
        return str_ends_with($key, '_minor') || $key === 'up_to';
    }

    public static function hidden(string $key): bool
    {
        return in_array($key, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'content', 'pdf_content', 'path', 'source_path', 'input_hash', 'http_status', 'security_version', 'file_sha256', 'sha256', 'links', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url']);
    }

    public static function decode(mixed $value): mixed
    {
        if (is_string($value) && in_array(substr($value, 0, 1), ['[', '{'])) {
            $decoded = json_decode($value, true, 20);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    public static function text(string $value): string
    {
        return ['draft' => 'Draf', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'in_review' => 'Dalam peninjauan', 'pending' => 'Menunggu', 'finalized' => 'Final', 'calculated' => 'Sudah dihitung', 'reviewed' => 'Sudah ditinjau', 'closed' => 'Ditutup', 'open' => 'Terbuka', 'paid' => 'Dibayar', 'failed' => 'Gagal', 'completed' => 'Selesai', 'queued' => 'Dalam antrean', 'ready' => 'Siap', 'committed' => 'Diterapkan', 'uploaded' => 'Diunggah', 'mapped' => 'Sudah dipetakan', 'validated' => 'Sudah divalidasi', 'cancelled' => 'Dibatalkan'][$value] ?? $value;
    }

    public static function labels(): array
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }
        $labels = ['id' => 'Referensi', 'version' => 'Versi', 'status' => 'Status', 'data' => 'Data', 'rows' => 'Rincian per karyawan', 'tasks' => 'Checklist', 'blockers' => 'Hal yang harus diselesaikan', 'warnings' => 'Peringatan untuk ditinjau', 'totals' => 'Total', 'snapshot' => 'Rekaman sumber', 'errors' => 'Kesalahan', 'mapping' => 'Pemetaan kolom', 'headers' => 'Kolom sumber', 'preview_checksum' => 'Checksum pratinjau', 'result' => 'Hasil', 'official_sources' => 'Sumber resmi', 'rules' => 'Aturan', 'public_id' => 'Referensi publik', 'row_count' => 'Jumlah baris', 'error_count' => 'Jumlah kesalahan', 'created_at' => 'Dibuat', 'updated_at' => 'Diperbarui', 'employee_name' => 'Nama karyawan', 'employee_number' => 'Nomor karyawan', 'employee_id' => 'Referensi karyawan', 'net_minor' => 'Gaji bersih', 'gross_minor' => 'Penghasilan bruto', 'deductions_minor' => 'Potongan', 'source_stale' => 'Sumber telah berubah', 'verified_at' => 'Waktu verifikasi', 'verified_by' => 'Peninjau', 'base_version' => 'Versi sumber', 'body_text' => 'Isi dokumen', 'kind' => 'Jenis'];
        $walk = function ($fields) use (&$walk, &$labels) {
            foreach ($fields as $field) {
                $labels[$field['name']] ??= $field['label'];
                if (isset($field['fields'])) {
                    $walk($field['fields']);
                }
            }
        };
        foreach (WorkspaceOperations::all() as $operation) {
            $walk($operation['fields']);
        }

        return $cached = $labels;
    }
}
