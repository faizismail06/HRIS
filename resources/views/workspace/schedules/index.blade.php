<x-identity-layout title="Kelola roster" subtitle="Susun jadwal tim, tinjau konflik, lalu publikasikan roster untuk karyawan.">
    <x-slot:actions>
        <a class="secondary-button" href="{{ route('workspace.attendance', ['view' => 'team']) }}">Kehadiran tim</a>
        <a class="primary-button" href="{{ route('workspace.schedules.create') }}"><x-icon name="plus" size="17"/>Susun roster</a>
    </x-slot:actions>
    <div class="live-roster">
        <nav class="attendance-subnav" aria-label="Konfigurasi jadwal">
            <a href="{{ route('workspace.schedules') }}" aria-current="page">Daftar roster</a>
            @if (App\Domain\Identity\Access::companyPermission(auth()->user(), 'schedules.manage'))
                <a href="{{ route('workspace.schedule-settings', 'locations') }}">Lokasi kerja</a>
                <a href="{{ route('workspace.schedule-settings', 'templates') }}">Template shift</a>
            @endif
        </nav>
        <section class="live-panel" aria-labelledby="roster-title">
            <header class="live-panel-heading"><div><h2 id="roster-title">Roster tim</h2><p>Draf dan jadwal yang telah dipublikasikan dalam cakupan akses Anda.</p></div><span class="attendance-page-count">{{ count($rows) }} roster ditampilkan</span></header>
            @if(count($rows))
                <p class="attendance-scroll-hint"><x-icon name="arrow" size="14"/>Geser tabel untuk melihat seluruh kolom.</p>
                <div class="attendance-table-wrap" role="region" aria-label="Daftar roster, dapat digeser horizontal" tabindex="0">
                    <table class="operational-table roster-table"><thead><tr><th scope="col">Roster</th><th scope="col">Periode jadwal</th><th scope="col">Jumlah shift</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
                        @foreach($rows as $row)
                            <tr><th scope="row"><a class="person-cell" href="{{ route('workspace.schedules.show', $row['record']->id) }}"><span class="summary-icon"><x-icon name="calendar" size="19"/></span><span>Roster #{{ $row['record']->id }}<small>Versi {{ $row['record']->version }}</small></span></a></th>
                                <td>
                                    @if($row['starts_on'])
                                        <span class="attendance-cell-main">{{ \Carbon\Carbon::parse($row['starts_on'])->locale('id')->translatedFormat('j M Y') }} — {{ \Carbon\Carbon::parse($row['ends_on'])->locale('id')->translatedFormat('j M Y') }}</span>
                                    @else
                                        Belum ada shift
                                    @endif
                                </td><td>{{ $row['count'] }} jadwal</td><td><span class="attendance-pill {{ $row['record']->status === 'draft' ? 'tone-scheduled' : 'tone-present' }}"><i aria-hidden="true"></i>{{ $row['record']->status === 'draft' ? 'Draf' : 'Dipublikasikan' }}</span></td><td><a class="attendance-detail-link" href="{{ route('workspace.schedules.show', $row['record']->id) }}">Buka roster<x-icon name="chevron" size="14"/></a></td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @else
                <div class="live-empty"><span class="empty-symbol"><x-icon name="calendar" size="26"/></span><h3>Belum ada roster yang terlihat</h3><p>Buat draf pertama atau lanjutkan ke halaman berikutnya jika tersedia.</p><a class="primary-button" href="{{ route('workspace.schedules.create') }}">Susun roster</a></div>
            @endif
            <footer class="live-panel-footer"><x-icon name="info" size="16"/><span>Draf belum terlihat oleh karyawan. Publikasi memeriksa kembali penempatan, konflik jadwal dan kecukupan staf.</span></footer>
        </section>
        @if($hasMore)
            <div class="attendance-pagination"><span>Masih ada roster yang dapat diperiksa.</span><a class="secondary-button" href="{{ route('workspace.schedules', ['cursor' => $cursor]) }}">Roster berikutnya<x-icon name="arrow" size="16"/></a></div>
        @endif
    </div>
</x-identity-layout>
