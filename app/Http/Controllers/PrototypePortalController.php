<?php

namespace App\Http\Controllers;

use App\Support\PrototypeAttendance;
use App\Support\PrototypeFinance;
use App\Support\PrototypeLifecycle;
use App\Support\PrototypePeople;
use App\Support\PrototypeRequests;
use Illuminate\View\View;

class PrototypePortalController extends Controller
{
    public const PERSONAS = ['employee' => 'Karyawan', 'manager' => 'Manager', 'hr' => 'HR', 'finance' => 'Finance', 'it' => 'IT & Aset', 'auditor' => 'Auditor', 'platform' => 'Admin platform'];

    public function __invoke(string $persona): View
    {
        abort_unless(isset(self::PERSONAS[$persona]), 404);
        $person = PrototypePeople::find($persona === 'manager' ? 'EMP-003' : 'EMP-004');
        $team = collect(PrototypePeople::employees())->where('manager', 'EMP-003')->whereIn('status', ['ACTIVE', 'OFFBOARDING']);
        $requests = collect(PrototypeRequests::all());
        $tasks = $persona === 'employee' ? $requests->where('employee', 'EMP-004') : $requests->filter(fn ($item) => $item['active_step'] && $item['active_step']['actor'] === 'Bima Prasetya');
        $next = collect(PrototypeAttendance::records())->where('person.id', 'EMP-004')->where('status', 'SCHEDULED')->sortBy('date')->first();
        $payroll = PrototypeFinance::payroll('PAY-SEP-26');
        $exceptions = collect(PrototypeAttendance::records())->whereIn('person.id', $team->pluck('id'))->filter(fn ($record) => $record['status'] === 'INCOMPLETE' || $record['late'] > 0 || $record['early'] > 0);
        $links = match ($persona) {
            'employee' => [self::link('Profil saya', 'prototype.employees.show', ['employee' => 'EMP-004']), self::link('Jadwal saya', 'prototype.schedules', ['employee' => 'EMP-004']), self::link('Saldo cuti', 'prototype.leave-balances', ['employee' => 'EMP-004']), self::link('Slip September contoh', 'prototype.payroll.payslip', ['batch' => 'PAY-SEP-26', 'employee' => 'EMP-004'])],
            'manager' => [self::link('Kalender tim contoh', 'prototype.team-calendar', ['branch' => 'Surabaya']), self::link('Organisasi contoh', 'prototype.organization'), self::link('Delegasi · layar statis', 'prototype.screen', ['screen' => 'delegation'])],
            'hr' => [self::link('Ringkasan HR', 'prototype.dashboard'), self::link('Kontrak', 'prototype.contracts'), self::link('Kesiapan penutupan', 'prototype.attendance-periods')],
            'finance' => [self::link('Batch payroll', 'prototype.payroll'), self::link('Klaim', 'prototype.reimbursements'), self::link('Rekonsiliasi · layar statis', 'prototype.screen', ['screen' => 'reconciliation'])],
            'it' => [self::link('Checklist periode', 'prototype.lifecycle'), self::link('Inventaris', 'prototype.assets'), self::link('Serah terima · layar statis', 'prototype.screen', ['screen' => 'asset-handover'])],
            'auditor' => [self::link('Audit contoh', 'prototype.audit'), self::link('Snapshot periode', 'prototype.attendance-periods'), self::link('Ekspor berizin · layar statis', 'prototype.screen', ['screen' => 'report-export'])],
            default => [self::link('Pengaturan', 'prototype.settings'), self::link('Akun & scope · layar statis', 'prototype.screen', ['screen' => 'account-access']), self::link('Demo terisolasi · layar statis', 'prototype.screen', ['screen' => 'demo'])],
        };
        $navigation = [self::link('Beranda', 'prototype.portal', ['persona' => $persona]) + ['current' => true], ...$links];

        return view('preview.portal', compact('persona', 'person', 'team', 'tasks', 'next', 'payroll', 'links', 'navigation', 'exceptions') + ['personas' => self::PERSONAS, 'ledger' => PrototypeRequests::ledger('EMP-004'), 'cases' => PrototypeLifecycle::cases()]);
    }

    private static function link(string $label, string $route, array $parameters = []): array
    {
        return compact('label', 'route', 'parameters');
    }
}
