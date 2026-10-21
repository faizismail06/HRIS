<?php

namespace App\Domain\Payroll;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Expenses\ExpenseService;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentService
{
    public function template(User $actor, string $key, array $input): array
    {
        PayrollService::authorize($actor);
        $d = Validator::make($input, ['code' => ['required', 'regex:/^[A-Z0-9_-]{2,30}$/'], 'columns' => ['required', 'array', 'min:3', 'max:12'], 'columns.*' => ['array:label,field'], 'columns.*.label' => ['required', 'string', 'max:80'], 'columns.*.field' => ['required', 'distinct', Rule::in(['batch_id', 'payment_id', 'employee_number', 'bank_code', 'account_number', 'account_name', 'amount_idr'])]])->validate();
        abort_if(array_diff(['batch_id', 'account_number', 'amount_idr'], array_column($d['columns'], 'field')), 422, 'Template wajib memuat batch_id, account_number dan amount_idr.');

        return Mutation::run($actor, 'bank.template', $key, $d, fn ($u) => PayrollService::authorize($u), function ($u) use ($d) {
            $version = (int) DB::table('bank_export_templates')->where('code', $d['code'])->max('version') + 1;
            $id = DB::table('bank_export_templates')->insertGetId(['code' => $d['code'], 'version' => $version, 'columns' => json_encode($d['columns']), 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'bank.template_drafted', 'bank_export_template', $id, ['version' => $version]);

            return ['id' => $id, 'version' => $version];
        });
    }

    public function confirm(User $actor, int $id, string $key): array
    {
        return Mutation::run($actor, 'bank.template.confirm', $key, compact('id'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($id) {
            $t = DB::table('bank_export_templates')->where('id', $id)->firstOrFail();
            abort_if($t->created_by === $u->id, 403);
            abort_if($t->confirmed_at, 409);
            DB::table('bank_export_templates')->where('id', $id)->update(['confirmed_by' => $u->id, 'confirmed_at' => now(), 'updated_at' => now()]);
            Audit::record($u->id, 'bank.template_confirmed', 'bank_export_template', $id, ['version' => $t->version]);

            return ['id' => $id, 'confirmed' => true];
        });
    }

    public function export(User $actor, int $batchId, int $templateId, string $key, string $reason): array
    {
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();

        return Mutation::run($actor, 'bank.export', $key, compact('batchId', 'templateId', 'reason'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($batchId, $templateId) {
            abort_if(app()->environment('demo') || config('hris.demo_features_enabled', false), 403, 'Bank export operasional tidak tersedia di demo.');
            $batch = DB::table('payroll_batches')->where('id', $batchId)->firstOrFail();
            $template = DB::table('bank_export_templates')->where('id', $templateId)->whereNotNull('confirmed_at')->firstOrFail();
            abort_unless($batch->status === 'finalized', 409);
            $existing = DB::table('bank_exports')->where('payroll_batch_id', $batchId)->first();
            if ($existing) {
                abort_unless($existing->bank_export_template_id === $templateId, 409, 'Batch sudah memiliki export; jangan membuat instruksi ganda dengan template lain.');

                return ['id' => $existing->id, 'public_id' => $existing->public_id, 'checksum' => $existing->sha256];
            }
            abort_if(DB::table('payroll_payment_outcomes')->join('payroll_rows', 'payroll_rows.id', '=', 'payroll_payment_outcomes.payroll_row_id')->where('payroll_rows.payroll_batch_id', $batchId)->exists(), 409, 'Pembayaran sudah direkonsiliasi; tidak dapat membuat instruksi batch baru.');
            $columns = json_decode($template->columns, true);
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, array_map(fn ($c) => self::safeCell($c['label']), $columns), ',', '"', '');
            $total = 0;
            $count = 0;
            foreach (DB::table('payroll_rows')->where('payroll_batch_id', $batchId)->orderBy('id')->get() as $row) {
                $snapshot = decrypt(DB::table('payroll_final_snapshots')->where('payroll_row_id', $row->id)->value('snapshot'));
                if ($snapshot['net_minor'] === 0) {
                    continue;
                }
                $bank = $snapshot['bank'];
                $values = ['batch_id' => $batch->public_id, 'payment_id' => $batch->public_id.':'.$row->id, 'employee_number' => $snapshot['employee_number'], 'bank_code' => $bank['bank_code'], 'account_number' => $bank['account_number'], 'account_name' => $bank['account_name'], 'amount_idr' => IntegerMoney::format($snapshot['net_minor'])];
                fputcsv($stream, array_map(fn ($c) => self::safeCell($values[$c['field']]), $columns), ',', '"', '');
                $total += $snapshot['net_minor'];
                $count++;
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
            $public = (string) Str::uuid();
            $checksum = hash('sha256', $content);
            $id = DB::table('bank_exports')->insertGetId(['public_id' => $public, 'payroll_batch_id' => $batchId, 'bank_export_template_id' => $templateId, 'content' => encrypt($content), 'sha256' => $checksum, 'control_total_minor' => encrypt($total), 'row_count' => $count, 'created_by' => $u->id, 'created_at' => now()]);
            Audit::record($u->id, 'bank.export_created', 'bank_export', $id, ['checksum' => $checksum, 'row_count' => $count, 'reason_provided' => true]);

            return ['id' => $id, 'public_id' => $public, 'checksum' => $checksum];
        });
    }

    public static function safeCell(string $value): string
    {
        return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }

    public function download(User $actor, int $id, string $reason)
    {
        PayrollService::authorize($actor, true);
        Validator::make(compact('reason'), ['reason' => ['required', 'string', 'max:1000']])->validate();
        abort_if(app()->environment('demo') || config('hris.demo_features_enabled', false), 403);
        $export = DB::table('bank_exports')->where('id', $id)->firstOrFail();
        $content = decrypt($export->content);
        abort_unless(hash_equals($export->sha256, hash('sha256', $content)), 503);
        Audit::record($actor->id, 'bank.export_downloaded', 'bank_export', $id, ['reason_provided' => true, 'checksum' => $export->sha256]);

        return response($content)->header('Content-Type', 'text/csv; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="bank-'.$export->public_id.'.csv"')->header('Cache-Control', 'no-store, private');
    }

    public function outcome(User $actor, int $rowId, string $key, array $input): array
    {
        PayrollService::authorize($actor, true);
        $d = Validator::make($input, ['external_event_id' => ['required', 'string', 'max:120'], 'status' => ['required', Rule::in(['paid', 'failed'])], 'amount_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'reference' => ['required', 'string', 'max:120'], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate();
        abort_if(($d['status'] === 'paid' && $d['amount_minor'] === 0) || ($d['status'] === 'failed' && $d['amount_minor'] !== 0), 422);

        return CoreWrite::run(fn () => Mutation::run($actor, 'payroll.payment.outcome', $key, compact('rowId', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($rowId, $d) {
            $row = DB::table('payroll_rows')->where('id', $rowId)->firstOrFail();
            abort_unless(DB::table('payroll_batches')->where('id', $row->payroll_batch_id)->where('status', 'finalized')->exists(), 409);
            $existing = DB::table('payroll_payment_outcomes')->where('external_event_id', $d['external_event_id'])->first();
            if ($existing) {
                foreach ($d as $field => $value) {
                    abort_unless($existing->{$field} == $value, 409, 'Event pembayaran sudah direkam dengan isian berbeda.');
                }
                abort_unless($existing->payroll_row_id === $rowId, 409);

                return ['id' => $existing->id, 'payroll_row_id' => $rowId];
            }
            abort_if(DB::table('payroll_payment_outcomes')->where('payroll_row_id', $rowId)->where('status', $d['status'])->where('reference', $d['reference'])->exists(), 409, 'Referensi pembayaran sudah direkam.');
            $snapshot = decrypt(DB::table('payroll_final_snapshots')->where('payroll_row_id', $rowId)->value('snapshot'));
            $paid = (int) DB::table('payroll_payment_outcomes')->where('payroll_row_id', $rowId)->where('status', 'paid')->sum('amount_minor');
            abort_if($paid + $d['amount_minor'] > $snapshot['net_minor'], 422, 'Pembayaran melebihi sisa net payroll.');
            $id = DB::table('payroll_payment_outcomes')->insertGetId($d + ['payroll_row_id' => $rowId, 'recorded_by' => $u->id, 'created_at' => now()]);
            if ($d['status'] === 'paid') {
                app(ExpenseService::class)->payrollSettlement($id, $snapshot, $paid + $d['amount_minor'], $u);
            }
            Audit::record($u->id, 'payroll.payment_reconciled', 'payroll_payment_outcome', $id, ['payroll_row_id' => $rowId, 'status' => $d['status']]);

            return ['id' => $id, 'payroll_row_id' => $rowId, 'payment_status' => $paid + $d['amount_minor'] === $snapshot['net_minor'] ? 'PAID' : (($paid + $d['amount_minor']) > 0 ? 'PARTIAL' : ($d['status'] === 'failed' ? 'FAILED' : 'UNPAID'))];
        }));
    }
}
