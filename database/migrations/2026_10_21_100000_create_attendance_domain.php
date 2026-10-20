<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('code', 40)->unique();
            $t->string('name', 160);
            $t->string('timezone', 80);
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->unsignedInteger('radius_m')->default(100);
            $t->unsignedInteger('max_accuracy_m')->default(100);
            $t->boolean('requires_photo')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE work_locations ADD CONSTRAINT valid_geofence CHECK(latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180 AND radius_m BETWEEN 1 AND 10000 AND max_accuracy_m BETWEEN 1 AND 1000)');
        Schema::create('shift_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40);
            $t->unsignedInteger('version');
            $t->string('name', 160);
            $t->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $t->time('starts_local');
            $t->time('ends_local');
            $t->unsignedSmallInteger('end_day_offset');
            $t->jsonb('unpaid_breaks');
            $t->unsignedInteger('half_day_split_minute')->nullable();
            $t->unsignedSmallInteger('tolerance_minutes')->default(0);
            $t->unsignedSmallInteger('clock_in_before_minutes')->default(120);
            $t->unsignedSmallInteger('clock_out_after_minutes')->default(240);
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        DB::statement('ALTER TABLE shift_templates ADD CONSTRAINT valid_shift_day_offset CHECK(end_day_offset IN (0,1))');
        Schema::create('roster_versions', function (Blueprint $t) {
            $t->id();
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->char('checksum', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('published_at')->nullable();
            $t->foreignId('supersedes_id')->nullable()->constrained('roster_versions')->restrictOnDelete();
            $t->text('reason')->nullable();
            $t->timestampsTz();
        });
        Schema::create('roster_shifts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('roster_version_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('work_date');
            $t->boolean('is_day_off')->default(false);
            $t->timestampTz('starts_at')->nullable();
            $t->timestampTz('ends_at')->nullable();
            $t->string('timezone', 80);
            $t->jsonb('snapshot');
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE roster_shifts ADD CONSTRAINT valid_shift_range CHECK((is_day_off AND starts_at IS NULL AND ends_at IS NULL) OR (NOT is_day_off AND starts_at IS NOT NULL AND ends_at > starts_at)), ADD CONSTRAINT no_overlapping_published_roster EXCLUDE USING gist (employee_id WITH =, tstzrange(starts_at,ends_at,'[)') WITH &&) WHERE (status='published' AND NOT is_day_off)");
        DB::statement("CREATE UNIQUE INDEX one_published_roster_work_date ON roster_shifts(employee_id,work_date) WHERE status='published'");
        Schema::create('work_calendar_dates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->date('work_date');
            $t->string('label', 160);
            $t->boolean('is_holiday');
            $t->unsignedInteger('version');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['branch_id', 'work_date', 'version']);
        });
        Schema::create('attendance_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('roster_shift_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('direction', 8);
            $t->timestampTz('occurred_at');
            $t->timestampTz('received_at');
            $t->text('coordinates');
            $t->foreignId('private_attachment_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('provenance', 30)->default('DEVICE_REPORTED');
            $t->uuid('scenario_id')->nullable();
            $t->timestampTz('created_at');
            $t->unique(['roster_shift_id', 'direction']);
        });
        DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT attendance_direction CHECK(direction IN ('in','out'))");
        DB::unprepared('CREATE TRIGGER immutable_attendance_events BEFORE UPDATE OR DELETE ON attendance_events FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER immutable_attendance_truncate BEFORE TRUNCATE ON attendance_events FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
        Schema::create('attendance_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('roster_shift_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('direction', 8);
            $t->string('rejection_code', 40);
            $t->timestampTz('created_at');
        });
        Schema::create('attendance_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('roster_shift_id')->unique()->constrained()->restrictOnDelete();
            $t->string('status', 30);
            $t->unsignedInteger('work_minutes')->default(0);
            $t->unsignedInteger('late_minutes')->default(0);
            $t->unsignedInteger('early_minutes')->default(0);
            $t->timestampTz('effective_in')->nullable();
            $t->timestampTz('effective_out')->nullable();
            $t->jsonb('sources');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('attendance_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 20)->default('open');
            $t->unsignedInteger('version')->default(1);
            $t->char('review_checksum', 64)->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->boolean('payroll_finalized')->default(false);
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE attendance_periods ADD CONSTRAINT attendance_period_range CHECK(ends_on > starts_on), ADD CONSTRAINT no_overlapping_attendance_period EXCLUDE USING gist (branch_id WITH =, daterange(starts_on,ends_on,'[)') WITH &&)");
        Schema::create('attendance_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attendance_period_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->foreignId('roster_shift_id')->constrained()->restrictOnDelete();
            $t->jsonb('snapshot');
            $t->char('sha256', 64);
            $t->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['attendance_period_id', 'revision', 'roster_shift_id']);
        });
        DB::unprepared('CREATE TRIGGER immutable_attendance_snapshots BEFORE UPDATE OR DELETE ON attendance_snapshots FOR EACH ROW EXECUTE FUNCTION protect_hris_audit();');
        Schema::create('attendance_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('roster_shift_id')->constrained()->restrictOnDelete();
            $t->timestampTz('effective_in');
            $t->timestampTz('effective_out');
            $t->unsignedInteger('base_result_version');
            $t->timestampTz('created_at');
        });
        Schema::create('attendance_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('attendance_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->jsonb('payload');
            $t->string('status', 20)->default('pending');
            $t->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        foreach (['attendance_adjustments', 'attendance_corrections', 'attendance_snapshots', 'attendance_periods', 'attendance_results', 'attendance_attempts', 'attendance_events', 'work_calendar_dates', 'roster_shifts', 'roster_versions', 'shift_templates', 'work_locations'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
