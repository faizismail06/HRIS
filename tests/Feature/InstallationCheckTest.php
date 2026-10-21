<?php

namespace Tests\Feature;

use Tests\TestCase;

class InstallationCheckTest extends TestCase
{
    public function test_read_only_checks_pass_for_test_installation_and_production_gate_requires_secure_settings(): void
    {
        $this->artisan('hris:check')->expectsOutputToContain('PASS PostgreSQL PDO driver')->expectsOutputToContain('PASS Database environment marker matches')->assertSuccessful();
        $this->artisan('hris:check', ['--production' => true])->expectsOutputToContain('FAIL Production environment')->assertFailed();
    }
}
