<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_boundary', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('kind', 12);
            $t->uuid('scenario_id')->nullable();
            $t->timestampTz('scenario_time')->nullable();
            $t->timestampTz('created_at');
        });
        DB::table('installation_boundary')->insert(['id' => 1, 'kind' => config('hris.demo_features_enabled') ? 'demo' : 'production', 'scenario_id' => config('hris.demo_features_enabled') ? config('hris.demo_scenario_id') : null, 'created_at' => now()]);
        Schema::create('demo_personas', function (Blueprint $t) {
            $t->string('code', 40)->primary();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->uuid('scenario_id');
        });
        DB::statement("ALTER TABLE attendance_events ADD CONSTRAINT simulated_scenario_required CHECK (provenance <> 'SIMULATED' OR scenario_id IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance_events DROP CONSTRAINT simulated_scenario_required');
        Schema::dropIfExists('demo_personas');
        Schema::dropIfExists('installation_boundary');
    }
};
