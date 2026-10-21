<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_lot_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_credit_lot_id')->constrained()->restrictOnDelete();
            $t->foreignId('target_lot_id')->nullable()->constrained('leave_credit_lots')->restrictOnDelete();
            $t->string('kind', 20);
            $t->unsignedInteger('minutes');
            $t->text('reason');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        DB::statement("ALTER TABLE leave_lot_changes ADD CONSTRAINT valid_lot_change CHECK (minutes > 0 AND ((kind='debit' AND target_lot_id IS NULL) OR (kind='carry_out' AND target_lot_id IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX one_carry_per_lot ON leave_lot_changes(leave_credit_lot_id) WHERE kind='carry_out'");
        DB::unprepared('CREATE TRIGGER leave_lot_changes_immutable BEFORE UPDATE OR DELETE ON leave_lot_changes FOR EACH ROW EXECUTE FUNCTION protect_hris_audit(); CREATE TRIGGER leave_lot_changes_no_truncate BEFORE TRUNCATE ON leave_lot_changes FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_lot_changes');
    }
};
