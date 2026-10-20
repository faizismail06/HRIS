<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('kind');
            $t->string('file_sha256', 64);
            $t->jsonb('headers');
            $t->text('rows');
            $t->jsonb('mapping')->nullable();
            $t->jsonb('errors')->default('[]');
            $t->unsignedInteger('error_count')->default(0);
            $t->unsignedInteger('row_count');
            $t->string('status')->default('uploaded');
            $t->unsignedInteger('version')->default(1);
            $t->string('preview_checksum', 64)->nullable();
            $t->jsonb('result')->nullable();
            $t->timestampsTz();
            $t->unique(['created_by', 'kind', 'file_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
