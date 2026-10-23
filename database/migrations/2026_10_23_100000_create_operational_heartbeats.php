<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_heartbeats', function (Blueprint $table) {
            $table->string('service', 30)->primary();
            $table->timestampTz('seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_heartbeats');
    }
};
