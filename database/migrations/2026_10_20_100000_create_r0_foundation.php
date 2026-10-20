<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE companies ADD CONSTRAINT single_company CHECK (id = 1)');
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE departments ADD CONSTRAINT no_self_parent CHECK (parent_id IS NULL OR parent_id <> id)');
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['id', 'department_id']);
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('name');
            $table->string('work_email')->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });
        Schema::create('employment_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // Exclusive, as with assignment intervals.
            $table->boolean('offboarding')->default(false);
            $table->timestampsTz();
            $table->unique(['id', 'employee_id']);
        });
        DB::statement('ALTER TABLE employment_periods ADD CONSTRAINT valid_employment_dates CHECK (ends_on IS NULL OR ends_on > starts_on)');
        DB::statement("ALTER TABLE employment_periods ADD CONSTRAINT no_overlapping_employment EXCLUDE USING gist (employee_id WITH =, daterange(starts_on, ends_on, '[)') WITH &&)");
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('employment_period_id');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('job_id');
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestampsTz();
            $table->foreign(['employment_period_id', 'employee_id'])->references(['id', 'employee_id'])->on('employment_periods')->restrictOnDelete();
            $table->foreign(['job_id', 'department_id'])->references(['id', 'department_id'])->on('job_positions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT no_self_manager CHECK (manager_employee_id IS NULL OR manager_employee_id <> employee_id)');
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT valid_assignment_dates CHECK (valid_to IS NULL OR valid_to > valid_from)');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT no_overlapping_assignment EXCLUDE USING gist (employee_id WITH =, daterange(valid_from, valid_to, '[)') WITH &&)");
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('security_version')->default(1);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX users_email_case_insensitive ON users (lower(email))');
        Schema::create('role_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role');
            $table->string('scope');
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE role_grants ADD CONSTRAINT valid_role CHECK (role IN ('employee','manager','hr_officer','hr_admin','payroll_officer','finance_approver','it_asset_officer','auditor','platform_admin'))");
        DB::statement("ALTER TABLE role_grants ADD CONSTRAINT valid_role_scope CHECK ((role='employee' AND scope='self') OR (role='manager' AND scope='team') OR (role NOT IN ('employee','manager') AND scope IN ('company','branch','department')))");
        DB::statement("ALTER TABLE role_grants ADD CONSTRAINT scope_target CHECK ((scope IN ('self','team','company') AND branch_id IS NULL AND department_id IS NULL) OR (scope='branch' AND branch_id IS NOT NULL AND department_id IS NULL) OR (scope='department' AND department_id IS NOT NULL AND branch_id IS NULL))");
        DB::statement('ALTER TABLE role_grants ADD CONSTRAINT unique_role_grant UNIQUE NULLS NOT DISTINCT (user_id, role, scope, branch_id, department_id)');
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id')->nullable();
            $table->jsonb('changes')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
        });
        // PostgreSQL functions survive migrate:fresh table drops, so replace their definitions on rerun.
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_hris_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit entries are append-only'; END; $$; CREATE TRIGGER protect_hris_audit BEFORE UPDATE OR DELETE ON audit_entries FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER protect_hris_audit_truncate BEFORE TRUNCATE ON audit_entries FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
        DB::statement('DROP FUNCTION IF EXISTS protect_hris_audit()');
        Schema::dropIfExists('role_grants');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['is_active', 'security_version', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
        DB::statement('DROP INDEX IF EXISTS users_email_case_insensitive');
        foreach (['assignments', 'employment_periods', 'employees', 'job_positions', 'departments', 'branches', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
