<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_policies', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedInteger('version');
            $t->date('valid_from');
            $t->date('valid_until');
            $t->jsonb('rules');
            $t->jsonb('official_sources');
            $t->string('status')->default('draft');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::create('employee_compensations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('valid_from');
            $t->date('valid_until')->nullable();
            $t->text('monthly_base_minor');
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE employee_compensations ADD CONSTRAINT compensation_dates CHECK(valid_until IS NULL OR valid_until > valid_from)');
        DB::statement("ALTER TABLE employee_compensations ADD CONSTRAINT compensation_overlap EXCLUDE USING gist(employee_id WITH =, daterange(valid_from,valid_until,'[)') WITH &&)");
        Schema::create('payroll_batches', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('attendance_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_policy_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->string('source_checksum', 64)->nullable();
            $t->text('totals')->nullable();
            $t->jsonb('blockers')->default('[]');
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('finalized_at')->nullable();
            $t->timestampTz('release_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX one_live_payroll_per_attendance ON payroll_batches(attendance_period_id) WHERE status <> 'cancelled'");
        Schema::create('payroll_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->text('snapshot');
            $t->timestampsTz();
            $t->unique(['payroll_batch_id', 'employee_id']);
        });
        Schema::create('payroll_final_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_row_id')->unique()->constrained()->restrictOnDelete();
            $t->text('snapshot');
            $t->text('pdf_content');
            $t->string('sha256', 64);
            $t->timestampTz('created_at');
        });
        Schema::create('bank_export_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedInteger('version');
            $t->jsonb('columns');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('confirmed_at')->nullable();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::create('bank_exports', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('bank_export_template_id')->constrained()->restrictOnDelete();
            $t->text('content');
            $t->string('sha256', 64);
            $t->text('control_total_minor');
            $t->unsignedInteger('row_count');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['payroll_batch_id', 'bank_export_template_id']);
        });
        Schema::create('payroll_payment_outcomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_row_id')->constrained()->restrictOnDelete();
            $t->string('external_event_id')->unique();
            $t->string('status');
            $t->unsignedBigInteger('amount_minor');
            $t->string('reference');
            $t->date('paid_on');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        foreach (['payroll_final_snapshots', 'bank_exports', 'payroll_payment_outcomes'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER {$table}_no_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();");
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_final_payroll() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_TABLE_NAME = 'payroll_batches' THEN
        IF OLD.status = 'finalized' THEN RAISE EXCEPTION 'Finalized payroll is immutable'; END IF;
    ELSE
        IF EXISTS(SELECT 1 FROM payroll_batches WHERE id = OLD.payroll_batch_id AND status = 'finalized') THEN RAISE EXCEPTION 'Finalized payroll rows are immutable'; END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END; $$;
CREATE TRIGGER final_payroll_batch_guard BEFORE UPDATE OR DELETE ON payroll_batches FOR EACH ROW EXECUTE FUNCTION protect_final_payroll();
CREATE TRIGGER final_payroll_row_guard BEFORE UPDATE OR DELETE ON payroll_rows FOR EACH ROW EXECUTE FUNCTION protect_final_payroll();
SQL);
    }

    public function down(): void
    {
        foreach (['payroll_payment_outcomes', 'bank_exports', 'bank_export_templates', 'payroll_final_snapshots', 'payroll_rows', 'payroll_batches', 'employee_compensations', 'payroll_policies'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS protect_final_payroll()');
    }
};
