<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40);
            $t->unsignedInteger('version');
            $t->string('name', 120);
            $t->boolean('is_paid');
            $t->boolean('deducts_balance');
            $t->boolean('allows_half_day');
            $t->boolean('requires_evidence');
            $t->unsignedInteger('notice_days')->default(0);
            $t->unsignedInteger('minimum_tenure_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::create('leave_credit_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('type_code', 40);
            $t->unsignedSmallInteger('entitlement_year');
            $t->date('valid_from');
            $t->date('valid_until');
            $t->unsignedInteger('minutes');
            $t->string('source_key', 100)->unique();
            $t->string('source_type', 30);
            $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::statement('ALTER TABLE leave_credit_lots ADD CONSTRAINT valid_credit_dates CHECK(valid_until > valid_from AND minutes > 0)');
        Schema::create('leave_segments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('roster_shift_id')->constrained()->restrictOnDelete();
            $t->date('work_date');
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->unsignedInteger('minutes');
            $t->string('state', 20)->default('reserved');
            $t->timestampTz('created_at');
            $t->unique(['workflow_request_id', 'roster_shift_id']);
        });
        DB::statement("ALTER TABLE leave_segments ADD CONSTRAINT no_overlapping_leave EXCLUDE USING gist (employee_id WITH =, tstzrange(starts_at,ends_at,'[)') WITH &&) WHERE (state IN ('reserved','approved'))");
        Schema::create('leave_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_segment_id')->constrained()->restrictOnDelete();
            $t->foreignId('leave_credit_lot_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('minutes');
            $t->string('state', 20)->default('reserved');
            $t->timestampTz('created_at');
            $t->unique(['leave_segment_id', 'leave_credit_lot_id']);
        });
        Schema::create('leave_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('leave_credit_lot_id')->constrained()->restrictOnDelete();
            $t->foreignId('leave_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('kind', 20);
            $t->integer('minutes');
            $t->string('business_key', 190)->unique();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::unprepared('CREATE TRIGGER immutable_leave_ledger BEFORE UPDATE OR DELETE ON leave_ledger FOR EACH ROW EXECUTE FUNCTION protect_hris_audit();');
        Schema::create('leave_cancellations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('original_request_id')->constrained('workflow_requests')->restrictOnDelete();
            $t->string('status', 20)->default('pending');
            $t->timestampTz('created_at');
        });
        DB::statement("CREATE UNIQUE INDEX one_open_leave_cancellation ON leave_cancellations(original_request_id) WHERE status IN ('pending','approved')");
        Schema::table('attendance_adjustments', function (Blueprint $t) {
            $t->dropUnique(['workflow_request_id']);
            $t->unique(['workflow_request_id', 'attendance_period_id']);
        });
    }

    public function down(): void
    {
        foreach (['leave_cancellations', 'leave_ledger', 'leave_allocations', 'leave_segments', 'leave_credit_lots', 'leave_types'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('attendance_adjustments', function (Blueprint $t) {
            $t->dropUnique(['workflow_request_id', 'attendance_period_id']);
            $t->unique('workflow_request_id');
        });
    }
};
