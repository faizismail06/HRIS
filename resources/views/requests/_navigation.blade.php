<nav class="section-links attendance-navigation" aria-label="Pengajuan dan cuti">
    <a href="{{ route('prototype.requests', array_filter(\Illuminate\Support\Arr::only($filters, ['q', 'type', 'status', 'branch', 'employee']))) }}" @if($page === 'requests') aria-current="page" @endif>Daftar pengajuan</a>
    <a href="{{ route('prototype.leave-balances', array_filter(['employee' => $filters['employee']])) }}" @if($page === 'balances') aria-current="page" @endif>Saldo cuti</a>
    <a href="{{ route('prototype.team-calendar', array_filter(\Illuminate\Support\Arr::only($filters, ['week', 'branch', 'employee']))) }}" @if($page === 'calendar') aria-current="page" @endif>Kalender tim</a>
</nav>
