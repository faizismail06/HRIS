<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_change_requests', fn (Blueprint $t) => $t->foreignId('workflow_request_id')->nullable()->unique()->constrained()->restrictOnDelete());
        Schema::table('employment_contracts', fn (Blueprint $t) => $t->foreignId('renews_id')->nullable()->constrained('employment_contracts')->restrictOnDelete());
        Schema::table('contract_review_tasks', fn (Blueprint $t) => $t->unsignedInteger('version')->default(1));
        Schema::table('lifecycle_cases', function (Blueprint $t) {
            $t->text('closure_reason')->nullable();
            $t->jsonb('closure_snapshot')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('closed_at')->nullable();
        });
        Schema::table('asset_custodies', fn (Blueprint $t) => $t->string('closure_kind')->nullable());
        Schema::table('asset_settlement_cases', function (Blueprint $t) {
            $t->string('resolution')->nullable();
            $t->text('resolution_evidence')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('resolved_at')->nullable();
            $t->unsignedInteger('version')->default(1);
        });
        Schema::create('lifecycle_owner_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lifecycle_task_id')->constrained()->restrictOnDelete();
            $t->foreignId('before_owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('after_owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestampTz('created_at');
        });
        Schema::create('contract_recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_review_task_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('decision');
            $t->text('reason');
            $t->timestampTz('created_at');
        });
        foreach (['lifecycle_owner_changes', 'contract_recommendations'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION protect_hris_audit();");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_recommendations');
        Schema::dropIfExists('lifecycle_owner_changes');
        Schema::table('asset_settlement_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('resolved_by');
            $t->dropColumn(['resolution', 'resolution_evidence', 'resolved_at', 'version']);
        });
        Schema::table('contract_review_tasks', fn (Blueprint $t) => $t->dropColumn('version'));
        Schema::table('lifecycle_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('closed_by');
            $t->dropColumn(['closure_reason', 'closure_snapshot', 'closed_at']);
        });
        Schema::table('asset_custodies', fn (Blueprint $t) => $t->dropColumn('closure_kind'));
        Schema::table('employment_contracts', fn (Blueprint $t) => $t->dropConstrainedForeignId('renews_id'));
        Schema::table('employee_change_requests', fn (Blueprint $t) => $t->dropConstrainedForeignId('workflow_request_id'));
    }
};
