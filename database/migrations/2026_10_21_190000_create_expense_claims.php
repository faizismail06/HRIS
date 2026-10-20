<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_policies', function (Blueprint $t) {
            $t->id();
            $t->string('category');
            $t->unsignedInteger('version');
            $t->date('valid_from');
            $t->date('valid_until');
            $t->unsignedBigInteger('max_claim_minor');
            $t->unsignedBigInteger('max_month_minor');
            $t->unsignedInteger('minimum_tenure_days');
            $t->jsonb('job_ids');
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['category', 'version']);
        });
        Schema::create('expense_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('expense_policy_id')->constrained()->restrictOnDelete();
            $t->date('incurred_on');
            $t->unsignedBigInteger('amount_minor');
            $t->foreignId('cost_center_id')->constrained('departments')->restrictOnDelete();
            $t->string('status')->default('submitted');
            $t->boolean('duplicate_warning')->default(false);
            $t->jsonb('evidence_hashes');
            $t->timestampsTz();
        });
        Schema::create('expense_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_claim_id')->unique()->constrained()->restrictOnDelete();
            $t->string('channel');
            $t->foreignId('payroll_batch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status')->default('allocated');
            $t->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::statement("ALTER TABLE expense_allocations ADD CONSTRAINT expense_channel CHECK ((channel='payroll' AND payroll_batch_id IS NOT NULL) OR (channel='reimbursement' AND payroll_batch_id IS NULL))");
        Schema::create('expense_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_allocation_id')->constrained()->restrictOnDelete();
            $t->foreignId('payroll_payment_outcome_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('external_event_id')->unique();
            $t->unsignedBigInteger('amount_minor');
            $t->string('reference');
            $t->date('paid_on');
            $t->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['expense_allocation_id', 'reference']);
        });
        DB::statement('ALTER TABLE expense_settlements ADD CONSTRAINT positive_expense_payment CHECK(amount_minor > 0)');
        DB::unprepared('CREATE TRIGGER expense_settlements_immutable BEFORE UPDATE OR DELETE ON expense_settlements FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER expense_settlements_no_truncate BEFORE TRUNCATE ON expense_settlements FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER expense_policy_immutable BEFORE UPDATE OR DELETE ON expense_policies FOR EACH ROW EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        foreach (['expense_settlements', 'expense_allocations', 'expense_claims', 'expense_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
