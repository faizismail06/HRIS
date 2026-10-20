<?php

namespace Tests\Feature;

use App\Support\PrototypeRequests;
use App\Support\PrototypeWorkspace;
use Tests\TestCase;

class PrototypeWorkspaceTest extends TestCase
{
    private array $pages = ['/prototype/notifications', '/prototype/notifications/NTF-005', '/prototype/search?q=Nadia'];

    public function test_workspace_routes_require_preview_and_reject_production(): void
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

    public function test_workspace_is_read_only_with_privacy_headers_and_visible_tools(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        foreach ($this->pages as $page) {
            $this->get($page)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->post($page)->assertStatus(405);
        }
        $this->get('/prototype/dashboard')->assertOk()->assertSee(route('prototype.search'))->assertSee(route('prototype.notifications'))->assertSee('3 belum dibaca dalam contoh');
    }

    public function test_notification_filters_and_return_context_preserve_fixture_read_state(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $filters = ['q' => 'COR-026', 'state' => 'UNREAD', 'delivery' => 'FAILED'];
        $this->get(route('prototype.notifications', $filters))->assertOk()->assertSee('NTF-005')->assertDontSee('NTF-004');
        $this->get(route('prototype.notifications.show', array_merge($filters, ['notification' => 'NTF-005'])))
            ->assertOk()->assertSee(route('prototype.notifications', $filters))->assertSee(route('prototype.requests.show', ['requestId' => 'COR-026']))
            ->assertSee('2026-10-20 09:00 WIB')->assertSee('Kegagalan email tidak membatalkan');
        $this->assertSame('UNREAD', PrototypeWorkspace::notification('NTF-005')['state']);
        $this->assertSame(3, PrototypeWorkspace::unread());
        $this->assertSame('IN_REVIEW', PrototypeRequests::find('COR-026')['status']);
    }

    public function test_queued_delivery_has_no_attempt_and_messages_have_unique_event_keys(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $queued = PrototypeWorkspace::notification('NTF-004');
        $this->assertSame('QUEUED', $queued['delivery']);
        $this->assertSame(0, $queued['attempts']);
        $keys = array_column(PrototypeWorkspace::notifications(), 'key');
        $this->assertSame(count($keys), count(array_unique($keys)));
        $this->get('/prototype/notifications?state=ARCHIVED')->assertOk()->assertSee('NTF-001')->assertDontSee('NTF-006');
        $this->get('/prototype/notifications/NTF-004')->assertOk()->assertSee('Antre · jam tenang')->assertSee('Pengingat tidak memperpanjang');
        $this->get('/prototype/notifications')->assertOk()->assertSeeInOrder(['NTF-006', 'NTF-004', 'NTF-003', 'NTF-005', 'NTF-002', 'NTF-001']);
    }

    public function test_search_initial_state_exact_reference_category_and_case_insensitive_matching(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/search')->assertOk()->assertSee('Mulai dengan nama atau referensi')->assertDontSee('CUT-041 · Cuti');
        $this->get('/prototype/search?q=nadia&category=employees')->assertOk()->assertSee('EMP-004 · Nadia Pramesti')->assertDontSee('CTR-001 · Kontrak');
        $this->get('/prototype/search?q=CUT-041')->assertOk()->assertSee(route('prototype.requests.show', ['requestId' => 'CUT-041']))->assertSee('Dalam tinjauan');
        $this->get('/prototype/search?q=PAY-SEP-26&category=payroll')->assertOk()->assertSee(route('prototype.payroll.show', ['batch' => 'PAY-SEP-26']))->assertDontSee('Rp 17.500.000');
        $this->get('/prototype/search?q=ATT-EMP-004-19')->assertOk()->assertSee(route('prototype.attendance.show', ['record' => 'ATT-EMP-004-19', 'date' => '2026-10-19', 'employee' => 'EMP-004']));
    }

    public function test_search_projects_minimum_fields_and_does_not_index_private_payloads_or_amounts(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $entries = PrototypeWorkspace::entries();
        foreach ($entries as $entry) {
            $this->assertSame(['category', 'id', 'title', 'context', 'route', 'parameters', 'keywords'], array_keys($entry));
        }
        $encoded = json_encode($entries);
        $this->assertStringNotContainsString('example.test', $encoded);
        $this->assertStringNotContainsString('settlements', $encoded);
        $this->assertStringNotContainsString('EVD-DEMO', $encoded);
        $this->get('/prototype/search?q=6400000')->assertOk()->assertSee('Tidak ada hasil yang cocok');
        $this->get('/prototype/search?q=Cakupan%20tim%20minimum')->assertOk()->assertSee('Tidak ada hasil yang cocok');
        $this->get('/prototype/search?q=%3Cscript%3E')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
    }

    public function test_representative_search_targets_resolve_for_every_category(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $representatives = collect(PrototypeWorkspace::entries())->unique('category');
        $this->assertCount(count(PrototypeWorkspace::categories()), $representatives);
        foreach ($representatives as $entry) {
            $this->get(route($entry['route'], $entry['parameters']))->assertOk();
        }
    }

    public function test_empty_invalid_scope_and_unknown_notification_are_handled(): void
    {
        config(['hris.ui_preview_enabled' => true]);
        $this->get('/prototype/notifications/UNKNOWN')->assertNotFound();
        $this->getJson('/prototype/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/prototype/search?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/prototype/search?q=Nadia&category=private')->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->getJson('/prototype/notifications?delivery=bad')->assertUnprocessable()->assertJsonValidationErrors('delivery');
        $this->get('/prototype/notifications?q=no-match')->assertOk()->assertSee('Tidak ada pesan yang cocok');
        $this->get('/prototype/search?q=Nadia&category=payroll')->assertOk()->assertSee('Tidak ada hasil yang cocok')->assertSee(route('prototype.search', ['q' => 'Nadia']));
    }
}
