<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_periods', fn (Blueprint $t) => $t->string('activation_state')->default('active'));
        Schema::table('assignments', fn (Blueprint $t) => $t->timestampTz('superseded_at')->nullable());
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT no_overlapping_assignment');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT no_overlapping_assignment EXCLUDE USING gist (employee_id WITH =, daterange(valid_from, valid_to, '[)') WITH &&) WHERE(superseded_at IS NULL)");
        Schema::create('employment_access_revocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employment_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestampTz('revoked_at');
            $t->unique(['employment_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_access_revocations');
        // Preserve superseded history: rollback is blocked when restoring the old exclusion would overlap.
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT no_overlapping_assignment');
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT no_overlapping_assignment EXCLUDE USING gist (employee_id WITH =, daterange(valid_from, valid_to, '[)') WITH &&)");
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn('superseded_at'));
        Schema::table('employment_periods', fn (Blueprint $t) => $t->dropColumn('activation_state'));
    }
};
