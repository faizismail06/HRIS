<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->functions(true);
    }

    public function down(): void
    {
        $this->functions(false);
    }

    private function functions(bool $superseded): void
    {
        $sql = <<<'SQL'
CREATE OR REPLACE FUNCTION guard_hris_assignment() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p employment_periods%ROWTYPE;
BEGIN
    PERFORM id FROM companies WHERE id = 1 FOR UPDATE;
    --RETURN_SUPERSEDED
    SELECT * INTO p FROM employment_periods WHERE id = NEW.employment_period_id;
    IF p.id IS NULL OR NEW.valid_from < p.starts_on OR (p.ends_on IS NOT NULL AND (NEW.valid_to IS NULL OR NEW.valid_to > p.ends_on)) THEN
        RAISE EXCEPTION 'Assignment outside employment period' USING ERRCODE = '23514';
    END IF;
    IF EXISTS (WITH RECURSIVE managers(employee_id, span, path) AS (
        SELECT NEW.manager_employee_id, daterange(NEW.valid_from, NEW.valid_to, '[)'), ARRAY[NEW.employee_id]
        UNION ALL
        SELECT a.manager_employee_id, m.span * daterange(a.valid_from, a.valid_to, '[)'), m.path || m.employee_id
        FROM managers m JOIN assignments a ON a.employee_id = m.employee_id AND m.span && daterange(a.valid_from, a.valid_to, '[)')
        WHERE NOT m.employee_id = ANY(m.path) AND a.manager_employee_id IS NOT NULL AND a.id <> COALESCE(NEW.id, 0) /*ACTIVE_ASSIGNMENT*/
    ) SELECT 1 FROM managers WHERE employee_id = NEW.employee_id) THEN
        RAISE EXCEPTION 'Manager hierarchy cycle during assignment interval' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END; $$;
CREATE OR REPLACE FUNCTION guard_hris_period() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    PERFORM id FROM companies WHERE id = 1 FOR UPDATE;
    IF EXISTS (SELECT 1 FROM assignments WHERE employment_period_id = NEW.id /*ACTIVE_PERIOD_ASSIGNMENT*/ AND
        (valid_from < NEW.starts_on OR (NEW.ends_on IS NOT NULL AND (valid_to IS NULL OR valid_to > NEW.ends_on)))) THEN
        RAISE EXCEPTION 'Existing assignment outside updated employment period' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END; $$;
SQL;
        DB::unprepared(str_replace(['--RETURN_SUPERSEDED', '/*ACTIVE_ASSIGNMENT*/', '/*ACTIVE_PERIOD_ASSIGNMENT*/'], $superseded ? ['IF NEW.superseded_at IS NOT NULL THEN RETURN NEW; END IF;', 'AND a.superseded_at IS NULL', 'AND superseded_at IS NULL'] : ['', '', ''], $sql));
    }
};
