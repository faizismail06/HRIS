<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_field_definitions', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('label');
            $t->string('type');
            $t->jsonb('options')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('employee_private_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('category');
            $t->text('data');
            $t->string('national_id_hash', 64)->nullable()->unique();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('verified_at');
            $t->foreignId('workflow_request_id')->constrained()->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['employee_id', 'category']);
        });
        Schema::create('private_profile_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->restrictOnDelete();
            $t->string('category');
            $t->unsignedInteger('version');
            $t->text('before_data')->nullable();
            $t->text('after_data');
            $t->foreignId('workflow_request_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['employee_id', 'category', 'version']);
        });
        DB::unprepared('CREATE TRIGGER private_profile_revisions_immutable BEFORE UPDATE OR DELETE ON private_profile_revisions FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER private_profile_revisions_no_truncate BEFORE TRUNCATE ON private_profile_revisions FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        foreach (['private_profile_revisions', 'employee_private_profiles', 'profile_field_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
