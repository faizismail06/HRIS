<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_batches', function (Blueprint $t) {
            $t->id();
            $t->string('status', 12)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('snapshot');
            $t->string('source_checksum', 64);
            $t->text('review_evidence')->nullable();
            $t->timestampsTz();
        });
        Schema::create('expense_batch_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('expense_allocation_id')->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true);
        });
        DB::statement('CREATE UNIQUE INDEX one_active_expense_batch ON expense_batch_items(expense_allocation_id) WHERE active');
        Schema::create('expense_bank_instructions', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_id')->unique();
            $t->foreignId('expense_batch_id')->constrained()->restrictOnDelete();
            $t->foreignId('bank_export_template_id')->constrained()->restrictOnDelete();
            $t->text('content');
            $t->text('total_minor');
            $t->string('sha256', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        Schema::create('expense_instruction_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_bank_instruction_id')->constrained()->restrictOnDelete();
            $t->foreignId('expense_batch_item_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->unique(['expense_bank_instruction_id', 'expense_batch_item_id']);
        });
        Schema::create('expense_instruction_outcomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_instruction_item_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('expense_settlement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('external_event_id', 120)->unique();
            $t->string('status', 10);
            $t->unsignedBigInteger('amount_minor');
            $t->string('reference', 120);
            $t->date('paid_on');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::statement("ALTER TABLE expense_instruction_outcomes ADD CONSTRAINT valid_instruction_outcome CHECK((status='failed' AND amount_minor=0 AND expense_settlement_id IS NULL) OR (status='paid' AND amount_minor>0 AND expense_settlement_id IS NOT NULL))");
        foreach (['expense_bank_instructions', 'expense_instruction_items', 'expense_instruction_outcomes'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER {$table}_no_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();");
        }
    }

    public function down(): void
    {
        foreach (['expense_instruction_outcomes', 'expense_instruction_items', 'expense_bank_instructions', 'expense_batch_items', 'expense_batches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
