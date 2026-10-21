<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_exit_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->jsonb('snapshot');
            $t->timestampTz('created_at');
        });
        DB::unprepared('CREATE TRIGGER exit_reconciliation_immutable BEFORE UPDATE OR DELETE ON employment_exit_reconciliations FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER exit_reconciliation_no_truncate BEFORE TRUNCATE ON employment_exit_reconciliations FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_exit_reconciliations');
    }
};
