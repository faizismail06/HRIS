<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['companies', 'branches', 'departments', 'job_positions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedInteger('version')->default(1));
        }
        Schema::create('employee_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->unsignedInteger('base_version');
            $table->jsonb('payload');
            $table->date('effective_on')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE employee_change_requests ADD CONSTRAINT valid_change_kind CHECK (kind IN ('identity','assignment')), ADD CONSTRAINT valid_change_state CHECK ((status='pending' AND reviewed_by IS NULL AND reviewed_at IS NULL) OR (status IN ('approved','rejected') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)), ADD CONSTRAINT change_no_self_approval CHECK (reviewed_by IS NULL OR reviewed_by <> requested_by)");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_change_requests');
        foreach (['companies', 'branches', 'departments', 'job_positions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('version'));
        }
    }
};
