<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX unique_successful_payroll_reference ON payroll_payment_outcomes(payroll_row_id,reference) WHERE status='paid'");
        DB::statement('CREATE UNIQUE INDEX one_bank_export_instruction ON bank_exports(payroll_batch_id)');
        DB::unprepared("CREATE TRIGGER approved_payroll_policy_immutable BEFORE UPDATE OR DELETE ON payroll_policies FOR EACH ROW WHEN (OLD.status='approved') EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER confirmed_bank_template_immutable BEFORE UPDATE OR DELETE ON bank_export_templates FOR EACH ROW WHEN (OLD.confirmed_at IS NOT NULL) EXECUTE FUNCTION protect_hris_audit();");
        $this->guard(true);
        DB::statement('DROP TRIGGER final_payroll_row_guard ON payroll_rows');
        DB::statement('CREATE TRIGGER final_payroll_row_guard BEFORE INSERT OR UPDATE OR DELETE ON payroll_rows FOR EACH ROW EXECUTE FUNCTION protect_final_payroll()');
    }

    private function guard(bool $insert): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_final_payroll() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_TABLE_NAME = 'payroll_batches' THEN
        IF OLD.status = 'finalized' THEN RAISE EXCEPTION 'Finalized payroll is immutable'; END IF;
    ELSE
        IF TG_OP <> 'INSERT' AND EXISTS(SELECT 1 FROM payroll_batches WHERE id = OLD.payroll_batch_id AND status = 'finalized') THEN RAISE EXCEPTION 'Finalized payroll rows are immutable'; END IF;
        IF TG_OP <> 'DELETE' AND EXISTS(SELECT 1 FROM payroll_batches WHERE id = NEW.payroll_batch_id AND status = 'finalized') THEN RAISE EXCEPTION 'Cannot add or move rows to finalized payroll'; END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END; $$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS approved_payroll_policy_immutable ON payroll_policies; DROP TRIGGER IF EXISTS confirmed_bank_template_immutable ON bank_export_templates; DROP INDEX IF EXISTS unique_successful_payroll_reference; DROP INDEX IF EXISTS one_bank_export_instruction;');
        DB::statement('DROP TRIGGER final_payroll_row_guard ON payroll_rows');
        DB::statement('CREATE TRIGGER final_payroll_row_guard BEFORE UPDATE OR DELETE ON payroll_rows FOR EACH ROW EXECUTE FUNCTION protect_final_payroll()');
    }
};
