<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_periods', fn (Blueprint $t) => $t->date('offboarding_from')->nullable());
        Schema::create('report_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('security_version');
            $t->string('kind');
            $t->string('format');
            $t->jsonb('filters');
            $t->text('reason');
            $t->string('status')->default('queued');
            $t->text('content')->nullable();
            $t->text('scope_snapshot')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->timestampTz('expires_at');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::table('employment_periods', fn (Blueprint $t) => $t->dropColumn('offboarding_from'));
    }
};
