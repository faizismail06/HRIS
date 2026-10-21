<nav class="workspace-actions" aria-label="Hak cuti">
    @if (auth()->user()->employee_id)<a class="inline-link" href="{{ route('workspace.leave-balances') }}">Saldo saya</a>@endif
    @if (App\Domain\Identity\Access::companyPermission(auth()->user(), 'leave.manage'))
        <a class="inline-link" href="{{ route('workspace.leave') }}">Kredit karyawan</a><a class="inline-link" href="{{ route('workspace.leave.types') }}">Jenis cuti</a><a class="inline-link" href="{{ route('workspace.leave.grant') }}">Catat kredit</a><a class="inline-link" href="{{ route('workspace.leave.schedules') }}">Jadwal pemberian</a>
    @endif
</nav>
