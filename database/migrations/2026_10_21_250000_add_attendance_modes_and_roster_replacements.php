<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_locations', fn (Blueprint $t) => $t->string('attendance_mode')->default('onsite'));
        Schema::table('roster_versions', function (Blueprint $t) {
            $t->jsonb('replacements')->default('[]');
            $t->text('replacement_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('roster_versions', fn (Blueprint $t) => $t->dropColumn(['replacements', 'replacement_reason']));
        Schema::table('work_locations', fn (Blueprint $t) => $t->dropColumn('attendance_mode'));
    }
};
