<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_retrospectives', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('employee_change_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->date('retrospective_on');
            $t->date('apply_from');
            $t->text('snapshot');
            $t->foreignId('hr_reviewer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('finance_reviewer_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::unprepared('CREATE TRIGGER career_retrospective_immutable BEFORE UPDATE OR DELETE ON career_retrospectives FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER career_retrospective_no_truncate BEFORE TRUNCATE ON career_retrospectives FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        Schema::dropIfExists('career_retrospectives');
    }
};
