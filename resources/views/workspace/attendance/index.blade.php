<x-identity-layout title="Kehadiran dan jadwal">
    <p>Periksa jadwal dan catatan waktu berdasarkan tanggal kerja shift. Shift malam tetap mengikuti tanggal mulai kerjanya.</p>
    @if ($canSchedule)<p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.schedules') }}">Kelola roster</a></p>@endif
    <nav class="workspace-actions" aria-label="Pemeriksaan kehadiran"><a class="inline-link" href="{{ route('workspace.overtime') }}">Lembur aktual</a>@if (App\Domain\Identity\Access::companyPermission(auth()->user(), 'attendance.close'))<a class="inline-link" href="{{ route('workspace.periods') }}">Tinjau dan tutup periode</a>@endif</nav>
    <form class="identity-form" method="GET" action="{{ route('workspace.attendance') }}">
        <x-core-field name="view" label="Tampilkan" type="select"><option value="mine" @selected($view === 'mine')>Jadwal saya</option><option value="team" @selected($view === 'team')>Semua yang dapat saya akses</option></x-core-field>
        <x-core-field name="starts_on" label="Dari tanggal kerja" type="date" :value="$start" required />
        <x-core-field name="ends_on" label="Sampai tanggal kerja" type="date" :value="$end" help="Maksimal 31 hari." required />
        <button class="secondary-button" type="submit">Tampilkan jadwal</button>
    </form>
    <ul class="identity-directory">
        @forelse ($rows as $row)
            <li><a class="inline-link" href="{{ route('workspace.attendance.show', $row['shift']->id) }}">{{ $row['person']->name }} · {{ $row['shift']->work_date }}</a>
                <p>{{ $row['shift']->is_day_off ? 'Hari libur' : $row['shift']->starts_at->setTimezone($row['shift']->timezone)->format('d M H:i').' — '.$row['shift']->ends_at->setTimezone($row['shift']->timezone)->format('d M H:i') }} · {{ $row['shift']->timezone }}</p>
                <p>{{ $statuses[$row['result']->status] ?? $row['result']->status }} · Kerja {{ $row['result']->work_minutes }} menit</p>
            </li>
        @empty<li><h2>Belum ada jadwal yang terlihat</h2><p>Coba rentang tanggal lain. Jadwal yang belum dipublikasikan belum tampil; hubungi HR bila jadwal kerja Anda belum tersedia.</p></li>@endforelse
    </ul>
    @if ($hasMore)<p><a class="secondary-button" href="{{ route('workspace.attendance', ['starts_on' => $start, 'ends_on' => $end, 'view' => $view, 'cursor' => $cursor]) }}">Lihat jadwal berikutnya</a></p>@endif
</x-identity-layout>
