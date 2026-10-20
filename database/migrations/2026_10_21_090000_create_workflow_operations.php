<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutation_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('operation', 100);
            $t->uuid('key');
            $t->char('input_hash', 64);
            $t->jsonb('result');
            $t->timestampTz('created_at');
            $t->unique(['user_id', 'operation', 'key']);
        });
        Schema::create('workflow_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('kind', 40);
            $t->unsignedInteger('version');
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->bigInteger('min_amount_minor')->default(0);
            $t->bigInteger('max_amount_minor')->nullable();
            $t->integer('priority')->default(0);
            $t->jsonb('steps');
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['name', 'version']);
        });
        DB::statement('ALTER TABLE workflow_definitions ADD CONSTRAINT workflow_amount_range CHECK (min_amount_minor >= 0 AND (max_amount_minor IS NULL OR max_amount_minor >= min_amount_minor))');
        Schema::create('workflow_requests', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->string('kind', 40);
            $t->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('workflow_definition_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('draft');
            $t->text('payload');
            $t->jsonb('summary');
            $t->unsignedInteger('version')->default(1);
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampTz('finalized_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE workflow_requests ADD CONSTRAINT workflow_request_state CHECK(status IN ('draft','in_review','blocked','approved','rejected','withdrawn'))");
        Schema::create('workflow_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('position');
            $t->string('role', 40);
            $t->jsonb('approver_ids');
            $t->string('status', 20)->default('waiting');
            $t->timestampTz('activated_at')->nullable();
            $t->timestampTz('due_at')->nullable();
            $t->timestampTz('escalate_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
            $t->unique(['workflow_request_id', 'position']);
        });
        DB::statement("ALTER TABLE workflow_steps ADD CONSTRAINT workflow_step_state CHECK(status IN ('waiting','pending','blocked','approved','rejected','cancelled'))");
        Schema::create('workflow_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_step_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('decision', 12);
            $t->text('reason')->nullable();
            $t->timestampTz('created_at');
        });
        DB::statement("ALTER TABLE workflow_decisions ADD CONSTRAINT workflow_decision_value CHECK(decision IN ('approve','reject'))");
        Schema::create('outbox_events', function (Blueprint $t) {
            $t->id();
            $t->string('dedup_key', 190)->unique();
            $t->string('event', 80);
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('subject_type', 80);
            $t->unsignedBigInteger('subject_id');
            $t->timestampTz('created_at');
        });
        Schema::create('inbox_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outbox_event_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestampTz('read_at')->nullable();
            $t->timestampTz('archived_at')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('outbox_event_id')->constrained()->restrictOnDelete();
            $t->string('channel', 12);
            $t->string('status', 16)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestampTz('available_at');
            $t->timestampTz('lease_until')->nullable();
            $t->timestampTz('sent_at')->nullable();
            $t->string('failure_code')->nullable();
            $t->timestampsTz();
            $t->unique(['outbox_event_id', 'channel']);
        });
        Schema::create('private_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->string('subject_type', 40);
            $t->unsignedBigInteger('subject_id');
            $t->string('classification', 30);
            $t->string('path')->unique();
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->char('sha256', 64);
            $t->timestampTz('created_at');
        });
        Schema::create('account_suspensions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at')->nullable();
            $t->text('reason');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE account_suspensions ADD CONSTRAINT valid_suspension_interval CHECK(ends_at IS NULL OR ends_at > starts_at)');
    }

    public function down(): void
    {
        foreach (['account_suspensions', 'private_attachments', 'notification_deliveries', 'inbox_messages', 'outbox_events', 'workflow_decisions', 'workflow_steps', 'workflow_requests', 'workflow_definitions', 'mutation_receipts'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
