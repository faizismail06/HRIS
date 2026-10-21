<?php

namespace App\Domain\Expenses;

use App\Domain\CoreHR\CoreWrite;
use App\Domain\Demo\DemoBoundary;
use App\Domain\Identity\Audit;
use App\Domain\Operations\Mutation;
use App\Domain\Payroll\IntegerMoney;
use App\Domain\Payroll\PaymentService;
use App\Domain\Payroll\PayrollService;
use App\Models\Employee;
use App\Models\EmployeePrivateProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReimbursementBatches
{
    private function collect(array $ids): array
    {
        $rows = [];
        foreach ($ids as $id) {
            $a = DB::table('expense_allocations')->where('id', $id)->where('channel', 'reimbursement')->firstOrFail();
            $c = DB::table('expense_claims')->find($a->expense_claim_id);
            abort_unless(in_array($c->status, ['approved_for_payment', 'paid']), 409);
            $bank = EmployeePrivateProfile::where('employee_id', $c->employee_id)->where('category', 'bank')->first();
            abort_unless($bank, 422, 'Profil bank terverifikasi diperlukan.');
            $remaining = $c->amount_minor - (int) DB::table('expense_settlements')->where('expense_allocation_id', $id)->sum('amount_minor');
            $rows[] = ['allocation_id' => (int) $id, 'claim_id' => $c->id, 'employee_id' => $c->employee_id, 'employee_number' => Employee::findOrFail($c->employee_id)->employee_number, 'amount_minor' => $remaining, 'bank' => $bank->data, 'bank_version' => $bank->version];
        }

        return $rows;
    }

    public function create(User $actor, string $key, array $input): array
    {
        $d = Validator::make($input, ['allocation_ids' => ['required', 'array', 'min:1', 'max:500'], 'allocation_ids.*' => ['integer', 'distinct'], 'reason' => ['required', 'string', 'max:2000']])->validate();
        $d['allocation_ids'] = array_map('intval', $d['allocation_ids']);
        sort($d['allocation_ids']);

        return CoreWrite::run(fn () => Mutation::run($actor, 'expense.batch.create', $key, $d, fn ($u) => PayrollService::authorize($u, true), function ($u) use ($d) {
            $rows = $this->collect($d['allocation_ids']);
            abort_if(collect($rows)->contains(fn ($r) => $r['amount_minor'] <= 0), 422, 'Claim sudah lunas.');
            $id = DB::table('expense_batches')->insertGetId(['prepared_by' => $u->id, 'snapshot' => encrypt($rows), 'source_checksum' => hash('sha256', json_encode($rows)), 'review_evidence' => encrypt($d['reason']), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($d['allocation_ids'] as $allocation) {
                DB::table('expense_batch_items')->insert(['expense_batch_id' => $id, 'expense_allocation_id' => $allocation]);
            }
            Audit::record($u->id, 'expense.batch_prepared', 'expense_batch', $id, ['items' => count($rows)]);

            return ['id' => $id, 'version' => 1, 'status' => 'draft'];
        }));
    }

    public function decide(User $actor, int $id, int $version, string $key, array $input): array
    {
        $d = Validator::make($input, ['action' => ['required', 'in:approve,refresh,cancel'], 'evidence' => ['required', 'string', 'max:4000']])->validate();

        return Mutation::run($actor, 'expense.batch.decide', $key, compact('id', 'version', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($id, $version, $d) {
            $b = DB::table('expense_batches')->where('id', $id)->firstOrFail();
            CoreWrite::version($b->version, $version);
            abort_if($b->status === 'cancelled', 409);
            $items = DB::table('expense_batch_items')->where('expense_batch_id', $id)->get();
            if ($d['action'] === 'cancel') {
                abort_if($this->pending($id), 409, 'Rekonsiliasi semua instruksi bank terlebih dahulu.');
                DB::table('expense_batch_items')->where('expense_batch_id', $id)->update(['active' => false]);
                $changes = ['status' => 'cancelled'];
            } else {
                abort_if(DB::table('expense_bank_instructions')->where('expense_batch_id', $id)->exists(), 409, 'Setelah export, cancel/rebatch beralasan hanya sesudah seluruh outcome tercatat.');
                $rows = $this->collect($items->pluck('expense_allocation_id')->all());
                if ($d['action'] === 'approve') {
                    abort_if($b->prepared_by === $u->id || ($u->employee_id && in_array($u->employee_id, array_column($rows, 'employee_id'), true)), 403);
                    abort_unless($b->status === 'draft' && hash_equals($b->source_checksum, hash('sha256', json_encode($rows))), 409, 'Sumber berubah; refresh dan review ulang.');
                    $changes = ['status' => 'approved', 'approved_by' => $u->id];
                } else {
                    $changes = ['status' => 'draft', 'approved_by' => null, 'prepared_by' => $u->id, 'snapshot' => encrypt($rows), 'source_checksum' => hash('sha256', json_encode($rows))];
                }
            }
            DB::table('expense_batches')->where('id', $id)->update($changes + ['version' => $version + 1, 'review_evidence' => encrypt($d['evidence']), 'updated_at' => now()]);
            Audit::record($u->id, 'expense.batch_'.$d['action'], 'expense_batch', $id, ['evidence_provided' => true]);

            return ['id' => $id, 'version' => $version + 1, 'status' => $changes['status']];
        });
    }

    private function pending(int $batch): bool
    {
        return DB::table('expense_instruction_items')->join('expense_bank_instructions', 'expense_bank_instructions.id', '=', 'expense_instruction_items.expense_bank_instruction_id')->where('expense_bank_instructions.expense_batch_id', $batch)->whereNotIn('expense_instruction_items.id', DB::table('expense_instruction_outcomes')->select('expense_instruction_item_id'))->exists();
    }

    public function export(User $actor, int $id, string $key, array $input): array
    {
        $d = Validator::make($input, ['template_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:2000']])->validate();

        return Mutation::run($actor, 'expense.batch.export', $key, compact('id', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($id, $d) {
            abort_if(DemoBoundary::enabled() || app()->environment('demo'), 403);
            $b = DB::table('expense_batches')->where('id', $id)->where('status', 'approved')->firstOrFail();
            abort_if($this->pending($id), 409, 'Outcome instruksi sebelumnya belum lengkap.');
            $template = DB::table('bank_export_templates')->where('id', $d['template_id'])->whereNotNull('confirmed_at')->firstOrFail();
            $items = DB::table('expense_batch_items')->where('expense_batch_id', $id)->where('active', true)->orderBy('expense_allocation_id')->get();
            $rows = $this->collect($items->pluck('expense_allocation_id')->all());
            $frozen = collect(decrypt($b->snapshot))->keyBy('allocation_id');
            $public = (string) Str::uuid();
            $stream = fopen('php://temp', 'w+');
            $columns = json_decode($template->columns, true);
            abort_unless(in_array('payment_id', array_column($columns, 'field'), true), 422, 'Template reimbursement wajib memuat payment_id per item.');
            fputcsv($stream, array_map(fn ($c) => PaymentService::safeCell($c['label']), $columns), ',', '"', '');
            $payable = [];
            foreach ($rows as $row) {
                abort_unless($row['bank_version'] === $frozen[$row['allocation_id']]['bank_version'], 409, 'Bank berubah; rekonsiliasi dan buat batch baru dengan review.');
                if ($row['amount_minor'] <= 0) {
                    continue;
                }
                $values = ['batch_id' => $public, 'payment_id' => $public.':'.$row['allocation_id'], 'employee_number' => $row['employee_number'], 'bank_code' => $row['bank']['bank_code'], 'account_number' => $row['bank']['account_number'], 'account_name' => $row['bank']['account_name'], 'amount_idr' => IntegerMoney::format($row['amount_minor'])];
                fputcsv($stream, array_map(fn ($c) => PaymentService::safeCell((string) $values[$c['field']]), $columns), ',', '"', '');
                $payable[] = $row;
            }
            abort_unless($payable, 422, 'Seluruh claim sudah lunas.');
            rewind($stream);
            $bytes = stream_get_contents($stream);
            fclose($stream);
            $total = IntegerMoney::sum(0, ...array_column($payable, 'amount_minor'));
            $instruction = DB::table('expense_bank_instructions')->insertGetId(['public_id' => $public, 'expense_batch_id' => $id, 'bank_export_template_id' => $template->id, 'content' => encrypt(base64_encode($bytes)), 'total_minor' => encrypt($total), 'sha256' => hash('sha256', $bytes), 'created_by' => $u->id, 'created_at' => now()]);
            foreach ($payable as $row) {
                DB::table('expense_instruction_items')->insert(['expense_bank_instruction_id' => $instruction, 'expense_batch_item_id' => $items->firstWhere('expense_allocation_id', $row['allocation_id'])->id, 'amount_minor' => $row['amount_minor']]);
            }
            Audit::record($u->id, 'expense.bank_instruction_created', 'expense_bank_instruction', $instruction, ['checksum' => hash('sha256', $bytes), 'item_count' => count($payable), 'reason_provided' => true]);

            return ['id' => $instruction, 'public_id' => $public, 'checksum' => hash('sha256', $bytes), 'item_count' => count($payable)];
        });
    }

    public function outcome(User $actor, int $itemId, string $key, array $input): array
    {
        $d = Validator::make($input, ['external_event_id' => ['required', 'string', 'max:100'], 'status' => ['required', 'in:paid,failed'], 'amount_minor' => ['required', 'integer', 'between:0,1000000000000000'], 'reference' => ['required', 'string', 'max:120'], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate();

        return CoreWrite::run(fn () => Mutation::run($actor, 'expense.instruction.outcome', $key, compact('itemId', 'd'), fn ($u) => PayrollService::authorize($u, true), function ($u) use ($itemId, $d) {
            $old = DB::table('expense_instruction_outcomes')->where('expense_instruction_item_id', $itemId)->orWhere('external_event_id', $d['external_event_id'])->first();
            if ($old) {
                abort_unless($old->expense_instruction_item_id === $itemId, 409);
                foreach ($d as $field => $value) {
                    abort_unless($old->{$field} == $value, 409);
                }

                return ['id' => $old->id];
            }
            $item = DB::table('expense_instruction_items')->where('id', $itemId)->firstOrFail();
            abort_unless($d['status'] === 'paid' ? ($d['amount_minor'] > 0 && $d['amount_minor'] <= $item->amount_minor) : $d['amount_minor'] === 0, 422);
            $allocation = DB::table('expense_batch_items')->where('id', $item->expense_batch_item_id)->value('expense_allocation_id');
            $settlement = null;
            if ($d['status'] === 'paid') {
                $settlement = app(ExpenseService::class)->settle($u, $allocation, (string) Str::uuid(), array_replace($d, ['external_event_id' => 'batch:'.$d['external_event_id']]), $itemId)['id'];
            }
            $outcome = DB::table('expense_instruction_outcomes')->insertGetId($d + ['expense_instruction_item_id' => $itemId, 'expense_settlement_id' => $settlement, 'actor_id' => $u->id, 'created_at' => now()]);
            Audit::record($u->id, 'expense.instruction_reconciled', 'expense_instruction_outcome', $outcome, ['status' => $d['status']]);

            return ['id' => $outcome];
        }));
    }
}
