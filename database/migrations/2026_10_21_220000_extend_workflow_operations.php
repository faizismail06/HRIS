<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_definitions', fn (Blueprint $t) => $t->jsonb('deadline_calendar')->nullable());
        Schema::table('workflow_steps', function (Blueprint $t) {
            $t->jsonb('deadline_snapshot')->nullable();
            $t->jsonb('delegation_snapshot')->default('{}');
        });
        Schema::create('workflow_delegations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delegator_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $t->string('role');
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->jsonb('employee_ids');
            $t->text('reason');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        Schema::table('workflow_decisions', function (Blueprint $t) {
            $t->foreignId('workflow_delegation_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->boolean('email_enabled')->default(true);
            $t->boolean('in_app_enabled')->default(true);
            $t->string('timezone');
            $t->string('delivery_mode')->default('immediate');
            $t->string('digest_time', 5)->default('09:00');
            $t->string('quiet_start', 5)->nullable();
            $t->string('quiet_end', 5)->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::table('workflow_decisions', fn (Blueprint $t) => $t->dropConstrainedForeignId('workflow_delegation_id'));
        Schema::dropIfExists('workflow_delegations');
        Schema::table('workflow_steps', fn (Blueprint $t) => $t->dropColumn(['deadline_snapshot', 'delegation_snapshot']));
        Schema::table('workflow_definitions', fn (Blueprint $t) => $t->dropColumn('deadline_calendar'));
    }
};
