<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrototypePeopleTest extends TestCase
{
    private array $pages = ['/prototype/employees', '/prototype/employees/EMP-004', '/prototype/organization'];

    public function test_people_pages_require_preview_flag_and_safe_environment(): void
    {
        config(['hris.ui_preview_enabled' => false]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertNotFound();
        }

        config(['hris.ui_preview_enabled' => true]);
        $this->app['env'] = 'production';
        foreach ($this->pages as $page) {
            $this->get($page)->assertNotFound();
        }
    }

    public function test_directory_combines_search_department_branch_and_status(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->get('/prototype/employees?q=Nadia&department=operasional&branch=Jakarta&status=ACTIVE')
            ->assertOk()->assertSee('Nadia Pramesti')->assertSee('Menampilkan 1 dari 12')
            ->assertDontSee('Dimas Saputra')->assertDontSee('Bima Prasetya')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_directory_keeps_former_profiles_and_provides_empty_recovery(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->get('/prototype/employees?status=ENDED')
            ->assertOk()->assertSee('Intan Kusuma')->assertDontSee('Nadia Pramesti');
        $this->get('/prototype/employees?q=tidak-ada-nama-ini')
            ->assertOk()->assertSee('Tidak ada karyawan yang cocok')->assertSee('Tampilkan semua karyawan');
    }

    public function test_profile_shows_planned_change_separately_and_unknown_ids_are_not_found(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->get('/prototype/employees/EMP-004')
            ->assertOk()->assertSee('Koordinator Operasional')->assertSee('Supervisor Operasional')
            ->assertSee('Belum menggantikan jabatan saat ini.')->assertSee('1 November 2026');
        $this->get('/prototype/employees/EMP-006')->assertOk()->assertSee('Profil ini belum berstatus aktif');
        $this->get('/prototype/employees/UNKNOWN')->assertNotFound();
    }

    public function test_organization_counts_only_current_working_employees_and_filters_branch(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->get('/prototype/organization')
            ->assertOk()->assertSee('10 karyawan bekerja')->assertSee('Raka Adinata')
            ->assertDontSee('Maya Larasati')->assertDontSee('Intan Kusuma');
        $this->get('/prototype/organization?branch=Bandung')
            ->assertOk()->assertSee('2 karyawan bekerja')->assertSee('Arif Wicaksono')
            ->assertSee('Sinta Maharani')->assertDontSee('Nadia Pramesti');
    }

    public function test_directory_filters_survive_manager_and_report_navigation(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'Nadia', 'department' => 'operasional', 'branch' => 'Jakarta', 'status' => 'ACTIVE', 'sort' => 'joined'];
        $personUrl = route('prototype.employees.show', array_merge(['employee' => 'EMP-004'], $filters));
        $managerUrl = route('prototype.employees.show', array_merge(['employee' => 'EMP-003'], $filters));

        $this->get($personUrl)->assertOk()->assertSee($managerUrl);
        $this->get($managerUrl)->assertOk()
            ->assertSee($personUrl)->assertSee(route('prototype.employees', $filters));
    }

    public function test_invalid_filter_values_are_validated(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        $this->getJson('/prototype/employees?q[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/prototype/employees?sort=arbitrary')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/prototype/organization?branch=Unknown')->assertUnprocessable()->assertJsonValidationErrors('branch');
    }

    public function test_people_preview_has_no_mutations(): void
    {
        config(['hris.ui_preview_enabled' => true]);

        foreach ($this->pages as $page) {
            $this->post($page)->assertStatus(405);
        }
    }
}
