<x-identity-layout title="Kehadiran dan jadwal" subtitle="Pantau jadwal tim, waktu masuk dan pulang, serta catatan yang perlu diperiksa.">
    <x-slot:actions>
        <a class="secondary-button" href="{{ route('workspace.team-calendar') }}"><x-icon name="calendar" size="17"/>Kalender tim</a>
        @if($canSchedule)
            <a class="primary-button" href="{{ route('workspace.schedules') }}">Kelola roster<x-icon name="arrow" size="17"/></a>
        @endif
    </x-slot:actions>
    <div class="live-attendance">
        <nav class="attendance-subnav" aria-label="Pemeriksaan kehadiran">
            <a href="{{ route('workspace.attendance') }}" aria-current="page">Rekap kehadiran</a>
            <a href="{{ route('workspace.overtime') }}">Lembur aktual</a>
            @if (App\Domain\Identity\Access::companyPermission(auth()->user(), 'attendance.close'))
                <a href="{{ route('workspace.periods') }}">Penutupan periode</a>
            @endif
        </nav>
        <form class="identity-form attendance-filters" method="GET" action="{{ route('workspace.attendance') }}">
            <x-core-field name="view" label="Cakupan" type="select">
                <option value="mine" @selected($view === 'mine')>Jadwal saya</option>
                <option value="team" @selected($view === 'team')>Tim dalam akses saya</option>
            </x-core-field>
            <x-core-field name="starts_on" label="Dari tanggal kerja" type="date" :value="$start" required />
            <x-core-field name="ends_on" label="Sampai tanggal kerja" type="date" :value="$end" required />
            <button class="primary-button" type="submit"><x-icon name="search" size="16"/>Tampilkan jadwal</button>
            <div class="attendance-filter-footer">
                <div class="attendance-presets">
                    <span>Pilih cepat</span>
                    @foreach(['Hari ini' => [today()->toDateString(), today()->toDateString()], 'Kemarin' => [today()->subDay()->toDateString(), today()->subDay()->toDateString()], '7 hari ke depan' => [today()->toDateString(), today()->addDays(6)->toDateString()]] as $label => [$from, $until])
                        <a href="{{ route('workspace.attendance', ['starts_on' => $from, 'ends_on' => $until, 'view' => $view]) }}" @if($start === $from && $end === $until) aria-current="date" @endif>{{ $label }}</a>
                    @endforeach
                </div>
                <span>Rentang maksimal 31 hari</span>
            </div>
        </form>
        <section class="live-panel" aria-labelledby="attendance-title">
            <header class="live-panel-heading">
                <div>
                    <h2 id="attendance-title">Rekap kehadiran</h2>
                    <p>
                        {{ \Carbon\Carbon::parse($start)->locale('id')->translatedFormat('j M Y') }}
                        @if($start !== $end)
                            — {{ \Carbon\Carbon::parse($end)->locale('id')->translatedFormat('j M Y') }}
                        @endif
                        · {{ $view === 'mine' ? 'Jadwal saya' : 'Sesuai cakupan akses Anda' }}
                    </p>
                </div>
                <span class="attendance-page-count">{{ count($rows) }} catatan ditampilkan</span>
            </header>
            @if(count($rows))
                <div class="attendance-status-summary" aria-label="Status pada halaman ini">
                    <span>Pada halaman ini</span>
                    @foreach(collect($rows)->countBy(fn ($row) => $row['result']->status) as $status => $count)
                        <span><i class="attendance-dot tone-{{ strtolower($status) }}" aria-hidden="true"></i><strong>{{ $count }}</strong>{{ $statuses[$status] ?? $status }}</span>
                    @endforeach
                </div>
                <p class="attendance-scroll-hint"><x-icon name="arrow" size="14"/>Geser tabel untuk melihat seluruh kolom.</p>
                <div class="attendance-table-wrap" role="region" aria-label="Tabel kehadiran, dapat digeser horizontal" tabindex="0">
                    <table class="operational-table live-attendance-table">
                        <caption class="sr-only">Jadwal dan waktu efektif sesuai tanggal kerja. Ringkasan hanya mencakup catatan pada halaman ini.</caption>
                        <thead>
                            <tr><th scope="col">Karyawan</th><th scope="col">Tanggal kerja</th><th scope="col">Jadwal shift</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th><th scope="col">Durasi aktual</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @include('workspace.attendance._row', ['row' => $row])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="live-empty">
                    <span class="empty-symbol"><x-icon name="calendar" size="26"/></span>
                    <h3>Belum ada jadwal yang terlihat</h3>
                    <p>Coba rentang tanggal lain. Jadwal yang belum dipublikasikan belum tampil; hubungi HR bila jadwal kerja Anda belum tersedia.</p>
                    <a class="secondary-button" href="{{ route('workspace.attendance', ['starts_on' => today()->toDateString(), 'ends_on' => today()->toDateString(), 'view' => $view]) }}">Lihat jadwal hari ini</a>
                </div>
            @endif
            <footer class="live-panel-footer"><x-icon name="info" size="16"/><span>Waktu mengikuti zona shift. Shift malam memakai tanggal mulai kerja; waktu efektif dapat mencakup koreksi yang disetujui. Durasi ditampilkan setelah waktu masuk dan pulang tersedia.</span></footer>
        </section>
        @if($hasMore)
            <div class="attendance-pagination">
                <span>Masih ada catatan yang dapat diperiksa.</span>
                <a class="secondary-button" href="{{ route('workspace.attendance', ['starts_on' => $start, 'ends_on' => $end, 'view' => $view, 'cursor' => $cursor]) }}">Lihat jadwal berikutnya<x-icon name="arrow" size="16"/></a>
            </div>
        @endif
    </div>
</x-identity-layout>
