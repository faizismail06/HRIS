<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_change_requests', fn (Blueprint $table) => $table->jsonb('before_payload')->default('{}'));
    }

    public function down(): void
    {
        Schema::table('employee_change_requests', fn (Blueprint $table) => $table->dropColumn('before_payload'));
    }
};
