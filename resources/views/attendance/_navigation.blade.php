<nav class="section-links attendance-navigation" aria-label="Jadwal dan kehadiran">
    @php
        $navDate = in_array($page, ['schedules', 'reports']) ? $filters['week'] : ($filters['date'] ?? '2026-10-19');
        $navWeek = \Carbon\CarbonImmutable::parse($navDate)->startOfWeek()->toDateString();
        $navScope = array_filter(['branch' => $filters['branch'] ?? '', 'employee' => $filters['employee'] ?? '']);
    @endphp
    <a href="{{ route('prototype.attendance', array_merge(['date' => $navDate], $navScope, $page === 'attendance' ? array_filter(['status' => $filters['status']]) : [])) }}" @if($page === 'attendance') aria-current="page" @endif>Rekap kehadiran</a>
    <a href="{{ route('prototype.schedules', array_merge(['week' => $navWeek], $navScope)) }}" @if($page === 'schedules') aria-current="page" @endif>Jadwal mingguan</a>
    <a href="{{ route('prototype.attendance-reports', $page === 'reports' ? $filters : array_merge(['week' => $navWeek], $navScope)) }}" @if($page === 'reports') aria-current="page" @endif>Laporan</a>
    <a href="{{ route('prototype.attendance-periods') }}" @if($page === 'periods') aria-current="page" @endif>Penutupan periode</a>
</nav>
