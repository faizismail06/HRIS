<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_digest_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->date('local_date');
            $t->string('status')->default('pending');
            $t->timestampTz('lease_until')->nullable();
            $t->timestampsTz();
            $t->unique(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_runs');
    }
};
