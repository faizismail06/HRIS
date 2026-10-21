<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_openings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('tax_year');
            $t->date('through_on');
            $t->string('source_key', 100)->unique();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->text('snapshot');
            $t->string('status', 12)->default('pending');
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX one_active_payroll_opening ON payroll_openings(employee_id,tax_year) WHERE status IN ('pending','approved')");
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_payroll_opening() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
IF TG_OP='DELETE' OR OLD.status='approved' THEN RAISE EXCEPTION 'Payroll opening history is immutable'; END IF;
RETURN NEW; END; $$;
CREATE TRIGGER payroll_opening_guard BEFORE UPDATE OR DELETE ON payroll_openings FOR EACH ROW EXECUTE FUNCTION protect_payroll_opening();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_openings');
    }
};
