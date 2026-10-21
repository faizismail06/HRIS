<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', fn (Blueprint $t) => $t->string('kind', 12)->default('regular'));
        DB::statement('DROP INDEX one_live_payroll_per_attendance');
        DB::statement("CREATE UNIQUE INDEX one_live_payroll_per_attendance ON payroll_batches(attendance_period_id) WHERE status <> 'cancelled' AND kind='regular'");
        DB::statement("ALTER TABLE payroll_batches ADD CONSTRAINT valid_payroll_kind CHECK(kind IN ('regular','off_cycle'))");
        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->id();
            $t->string('source_key', 100)->unique();
            $t->foreignId('source_payroll_row_id')->constrained('payroll_rows')->restrictOnDelete();
            $t->foreignId('attendance_adjustment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('payroll_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->text('amount_minor');
            $t->text('reason');
            $t->string('status', 12)->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_evidence')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampsTz();
        });
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_payroll_adjustment() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Payroll adjustment history cannot be deleted'; END IF;
IF ROW(OLD.source_key,OLD.source_payroll_row_id,OLD.attendance_adjustment_id,OLD.payroll_batch_id,OLD.employee_id,OLD.amount_minor,OLD.reason,OLD.created_by) IS DISTINCT FROM ROW(NEW.source_key,NEW.source_payroll_row_id,NEW.attendance_adjustment_id,NEW.payroll_batch_id,NEW.employee_id,NEW.amount_minor,NEW.reason,NEW.created_by) THEN RAISE EXCEPTION 'Adjustment sources and amount are immutable'; END IF;
IF EXISTS(SELECT 1 FROM payroll_batches WHERE id=OLD.payroll_batch_id AND status='finalized') THEN RAISE EXCEPTION 'Final payroll adjustments are immutable'; END IF;
RETURN NEW; END; $$;
CREATE TRIGGER payroll_adjustment_guard BEFORE UPDATE OR DELETE ON payroll_adjustments FOR EACH ROW EXECUTE FUNCTION protect_payroll_adjustment();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        DB::statement('DROP INDEX one_live_payroll_per_attendance');
        DB::statement("CREATE UNIQUE INDEX one_live_payroll_per_attendance ON payroll_batches(attendance_period_id) WHERE status <> 'cancelled'");
        DB::statement('ALTER TABLE payroll_batches DROP CONSTRAINT valid_payroll_kind');
        Schema::table('payroll_batches', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
