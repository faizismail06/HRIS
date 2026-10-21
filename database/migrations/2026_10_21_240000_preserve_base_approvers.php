<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', fn (Blueprint $t) => $t->jsonb('base_approver_ids')->default('[]'));
        DB::statement('UPDATE workflow_steps SET base_approver_ids=approver_ids');
        DB::unprepared('CREATE TRIGGER workflow_decisions_immutable BEFORE UPDATE OR DELETE ON workflow_decisions FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER workflow_decisions_no_truncate BEFORE TRUNCATE ON workflow_decisions FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_decisions_immutable ON workflow_decisions; DROP TRIGGER IF EXISTS workflow_decisions_no_truncate ON workflow_decisions;');
        Schema::table('workflow_steps', fn (Blueprint $t) => $t->dropColumn('base_approver_ids'));
    }
};
