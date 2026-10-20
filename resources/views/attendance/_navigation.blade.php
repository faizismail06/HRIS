<nav class="section-links attendance-navigation" aria-label="Jadwal dan kehadiran">
    <a href="{{ route('prototype.attendance', array_filter(['date' => $page === 'schedules' ? $filters['week'] : $filters['date'], 'branch' => $filters['branch'], 'employee' => $filters['employee'], 'status' => $page === 'attendance' ? $filters['status'] : ''])) }}" @if($page === 'attendance') aria-current="page" @endif>Rekap kehadiran</a>
    <a href="{{ route('prototype.schedules', array_filter(['week' => $page === 'schedules' ? $filters['week'] : \Carbon\CarbonImmutable::parse($filters['date'])->startOfWeek()->toDateString(), 'branch' => $filters['branch'], 'employee' => $filters['employee']])) }}" @if($page === 'schedules') aria-current="page" @endif>Jadwal mingguan</a>
</nav>
