<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_authorizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->foreignId('roster_shift_id')->constrained()->restrictOnDelete();
            $t->timestampTz('planned_start');
            $t->timestampTz('planned_end');
            $t->string('status', 20)->default('pending');
            $t->timestampTz('actual_start')->nullable();
            $t->timestampTz('actual_end')->nullable();
            $t->unsignedInteger('payable_minutes')->default(0);
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('verified_at')->nullable();
            $t->unsignedInteger('attendance_version')->nullable();
            $t->text('exception_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement("ALTER TABLE overtime_authorizations ADD CONSTRAINT valid_overtime_window CHECK(planned_end > planned_start), ADD CONSTRAINT no_overlapping_overtime EXCLUDE USING gist(employee_id WITH =,tstzrange(planned_start,planned_end,'[)') WITH &&) WHERE (status IN ('pending','approved','verified'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_authorizations');
    }
};
