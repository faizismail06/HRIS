<x-identity-layout :title="'Roster #'.$record->id" :subtitle="'Versi '.$record->version.' · '.$shifts->total().' jadwal dalam roster ini.'">
    <x-slot:actions><a class="secondary-button" href="{{ route('workspace.schedules') }}">Kembali ke roster</a><a class="secondary-button" href="{{ route('workspace.schedules.show', $record->id) }}"><x-icon name="clock" size="16"/>Muat ulang rincian</a></x-slot:actions>
    <div class="live-roster">
        <div class="roster-state"><span class="attendance-pill {{ $record->status === 'draft' ? 'tone-scheduled' : 'tone-present' }}"><i aria-hidden="true"></i>{{ $record->status === 'draft' ? 'Draf · belum tersedia bagi karyawan' : 'Dipublikasikan' }}</span><span>Waktu mengikuti zona masing-masing shift.</span></div>
        @if($record->replacements)
            <section class="identity-section"><h2>Penggantian jadwal</h2><p>{{ $record->replacement_reason }}</p><p class="history-note">{{ count($record->replacements) }} jadwal lama diganti saat publikasi; riwayat tetap disimpan.</p></section>
        @endif
        @if($warnings)
            <section class="identity-section staffing-warnings"><h2>Peringatan staffing</h2><ul class="identity-directory">
                @foreach($warnings as $warning)
                    <li>{{ $warning['work_date'] }} · cakupan minimum {{ $warning['actual'] }} dari kebutuhan {{ $warning['required'] }} orang<p>{{ $warning['blocking'] ? 'Wajib diselesaikan sebelum publikasi.' : 'Tinjau dan akui peringatan sebelum publikasi.' }}</p></li>
                @endforeach
            </ul></section>
        @endif
        <section class="live-panel" aria-labelledby="roster-shifts-title">
            <header class="live-panel-heading"><div><h2 id="roster-shifts-title">Jadwal karyawan</h2><p>Tinjau karyawan, tanggal kerja dan jam shift sebelum melanjutkan.</p></div><span class="attendance-page-count">{{ $shifts->count() }} dari {{ $shifts->total() }} jadwal</span></header>
            <p class="attendance-scroll-hint"><x-icon name="arrow" size="14"/>Geser tabel untuk melihat seluruh kolom.</p>
            <div class="attendance-table-wrap" role="region" aria-label="Jadwal dalam roster, dapat digeser horizontal" tabindex="0">
                <table class="operational-table roster-shifts-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Tanggal kerja</th><th scope="col">Jadwal shift</th><th scope="col">Zona waktu</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
                    @foreach($shifts as $shift)
                        @php($personName = $people[$shift->employee_id] ?? 'Karyawan')
                        <tr><th scope="row"><span class="person-cell"><span class="avatar">{{ mb_strtoupper(mb_substr($personName, 0, 1)) }}</span>{{ $personName }}</span></th><td>{{ \Carbon\Carbon::parse($shift->work_date)->locale('id')->translatedFormat('D, j M Y') }}</td><td>
                            @if($shift->is_day_off)
                                <span class="attendance-pill tone-day_off">Hari libur</span>
                            @else
                                <span class="attendance-cell-main">{{ $shift->starts_at->setTimezone($shift->timezone)->format('H:i') }}–{{ $shift->ends_at->setTimezone($shift->timezone)->format('H:i') }}</span>
                                @if($shift->ends_at->setTimezone($shift->timezone)->toDateString() !== $shift->work_date)
                                    <small class="roster-next-day">Pulang hari berikutnya</small>
                                @endif
                            @endif
                        </td><td>{{ $shift->timezone }}</td><td>
                            @if($shift->status === 'published')
                                <a class="attendance-detail-link" href="{{ route('workspace.attendance.show', $shift->id) }}">Buka kehadiran<x-icon name="chevron" size="14"/></a>
                            @else
                                <span class="attendance-muted">{{ $shift->status === 'superseded' ? 'Jadwal telah diganti' : 'Belum dipublikasikan' }}</span>
                            @endif
                        </td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </section>
        <div class="operational-pagination">{{ $shifts->links() }}</div>
        @if($record->status === 'draft')
            <section class="live-panel schedule-publish" aria-labelledby="publish-title"><div class="live-panel-heading"><div><h2 id="publish-title">Publikasikan jadwal</h2><p>Publikasi memeriksa ulang akses, penempatan, konflik roster dan coverage staffing. Jadwal tidak dipublikasikan jika pemeriksaan gagal.</p></div></div>
                <form class="identity-form" method="POST" action="{{ route('workspace.schedules.publish', $record->id) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="expected_version" value="{{ $record->version }}">
                    <input type="hidden" name="checksum" value="{{ $record->checksum }}">
                    @if($warnings)
                        <label class="roster-checkbox"><input type="checkbox" name="acknowledge_warnings" value="1"><span>Saya sudah meninjau peringatan staffing. Peringatan wajib tetap harus diselesaikan.</span></label>
                    @endif
                    <button class="primary-button" type="submit">Publikasikan roster<x-icon name="arrow" size="16"/></button>
                </form>
            </section>
        @endif
    </div>
</x-identity-layout>
