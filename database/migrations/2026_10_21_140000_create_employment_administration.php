<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedInteger('version');
            $t->string('kind');
            $t->string('title');
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->id();
            $t->string('series');
            $t->unsignedInteger('year');
            $t->unsignedBigInteger('next_value')->default(1);
            $t->unique(['series', 'year']);
        });
        Schema::create('issued_documents', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->string('kind');
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('employment_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('document_template_id')->constrained()->restrictOnDelete();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('reissues_id')->nullable()->constrained('issued_documents')->restrictOnDelete();
            $t->jsonb('snapshot');
            $t->text('pdf_content');
            $t->string('sha256', 64);
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('issued_at');
        });
        DB::unprepared('CREATE TRIGGER issued_documents_immutable BEFORE UPDATE OR DELETE ON issued_documents FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER issued_documents_no_truncate BEFORE TRUNCATE ON issued_documents FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
        Schema::create('employment_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('employment_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('issued_document_id')->nullable()->constrained('issued_documents')->restrictOnDelete();
            $t->foreignId('amends_id')->nullable()->constrained('employment_contracts')->restrictOnDelete();
            $t->string('contract_type');
            $t->unsignedInteger('renewal_sequence');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status')->default('in_review');
            $t->text('terms');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE employment_contracts ADD CONSTRAINT valid_contract_dates CHECK(ends_on > starts_on)');
        DB::statement("ALTER TABLE employment_contracts ADD CONSTRAINT contracts_primary_overlap EXCLUDE USING gist(employee_id WITH =, daterange(starts_on, ends_on, '[)') WITH &&) WHERE(status='approved' AND amends_id IS NULL)");
        Schema::create('contract_review_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employment_contract_id')->unique()->constrained()->restrictOnDelete();
            $t->string('status')->default('open');
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('recommendation')->nullable();
            $t->timestampsTz();
        });
        Schema::create('lifecycle_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedInteger('version');
            $t->string('kind');
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('job_positions')->restrictOnDelete();
            $t->jsonb('tasks');
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::create('lifecycle_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('employment_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('lifecycle_template_id')->constrained()->restrictOnDelete();
            $t->foreignId('workflow_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->string('status')->default('open');
            $t->date('effective_on');
            $t->date('last_working_on')->nullable();
            $t->string('reason_class')->nullable();
            $t->text('reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX one_open_lifecycle_case ON lifecycle_cases(employment_period_id,kind) WHERE status IN ('open','in_review','approved')");
        Schema::create('lifecycle_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lifecycle_case_id')->constrained()->restrictOnDelete();
            $t->string('code');
            $t->string('label');
            $t->string('role');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->boolean('mandatory');
            $t->jsonb('dependencies');
            $t->date('due_on');
            $t->string('status')->default('open');
            $t->text('evidence')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('completed_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
            $t->unique(['lifecycle_case_id', 'code']);
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('serial')->nullable()->unique();
            $t->string('category');
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('condition');
            $t->string('status')->default('available');
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        Schema::create('asset_custodies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->timestampTz('issued_at');
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->text('issue_evidence');
            $t->timestampTz('accepted_at')->nullable();
            $t->timestampTz('returned_at')->nullable();
            $t->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('return_evidence')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX asset_one_open_custody ON asset_custodies(asset_id) WHERE returned_at IS NULL');
        Schema::create('asset_settlement_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_custody_id')->constrained()->restrictOnDelete();
            $t->string('classification');
            $t->text('evidence');
            $t->string('status')->default('open');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('consumable_issues', function (Blueprint $t) {
            $t->id();
            $t->string('item_code');
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->text('evidence');
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('issued_at');
        });
    }

    public function down(): void
    {
        foreach (['consumable_issues', 'asset_settlement_cases', 'asset_custodies', 'assets', 'lifecycle_tasks', 'lifecycle_cases', 'lifecycle_templates', 'contract_review_tasks', 'employment_contracts', 'issued_documents', 'document_sequences', 'document_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
