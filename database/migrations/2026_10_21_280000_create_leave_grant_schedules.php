<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_grant_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $t->string('name', 120);
            $t->string('cadence', 10);
            $t->date('first_grant_on');
            $t->date('ends_on');
            $t->unsignedInteger('minutes');
            $t->unsignedInteger('minimum_tenure_days');
            $t->jsonb('employee_ids');
            $t->text('evidence');
            $t->string('status', 12)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampTz('stopped_at')->nullable();
            $t->text('stop_reason')->nullable();
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE leave_grant_schedules ADD CONSTRAINT valid_grant_schedule CHECK (ends_on > first_grant_on AND minutes > 0 AND cadence IN ('annual','monthly') AND status IN ('draft','approved','stopped'))");
        Schema::create('leave_grant_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_grant_schedule_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('grant_on');
            $t->foreignId('leave_credit_lot_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('outcome', 30);
            $t->timestampTz('created_at');
            $t->unique(['leave_grant_schedule_id', 'employee_id', 'grant_on']);
        });
        DB::unprepared('CREATE TRIGGER leave_grant_runs_immutable BEFORE UPDATE OR DELETE ON leave_grant_runs FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER leave_grant_runs_no_truncate BEFORE TRUNCATE ON leave_grant_runs FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_grant_runs');
        Schema::dropIfExists('leave_grant_schedules');
    }
};
