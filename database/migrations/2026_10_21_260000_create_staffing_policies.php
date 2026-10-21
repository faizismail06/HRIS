<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffing_policies', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedInteger('version');
            $t->foreignId('work_location_id')->constrained()->restrictOnDelete();
            $t->foreignId('job_id')->nullable()->constrained('job_positions')->restrictOnDelete();
            $t->date('valid_from');
            $t->date('valid_until');
            $t->jsonb('working_days');
            $t->string('start_local', 5);
            $t->string('end_local', 5);
            $t->unsignedSmallInteger('end_day_offset');
            $t->unsignedSmallInteger('minimum_staff');
            $t->boolean('is_blocking');
            $t->boolean('is_active')->default(true);
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['code', 'version']);
        });
        Schema::table('roster_versions', fn (Blueprint $t) => $t->jsonb('staffing_snapshot')->nullable());
    }

    public function down(): void
    {
        Schema::table('roster_versions', fn (Blueprint $t) => $t->dropColumn('staffing_snapshot'));
        Schema::dropIfExists('staffing_policies');
    }
};
