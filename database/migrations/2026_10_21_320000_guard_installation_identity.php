<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_installation_boundary() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
IF TG_OP='DELETE' OR ROW(OLD.id,OLD.kind,OLD.scenario_id) IS DISTINCT FROM ROW(NEW.id,NEW.kind,NEW.scenario_id) THEN RAISE EXCEPTION 'Installation identity cannot change'; END IF;
RETURN NEW; END; $$;
CREATE TRIGGER installation_boundary_identity BEFORE UPDATE OR DELETE ON installation_boundary FOR EACH ROW EXECUTE FUNCTION protect_installation_boundary();
CREATE TRIGGER installation_boundary_no_truncate BEFORE TRUNCATE ON installation_boundary FOR EACH STATEMENT EXECUTE FUNCTION protect_hris_audit();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS installation_boundary_identity ON installation_boundary; DROP TRIGGER IF EXISTS installation_boundary_no_truncate ON installation_boundary;');
    }
};
