<x-app-layout title="Jadwal mingguan" active="attendance">
    <div class="page-heading"><div><h1>Rencana kerja tim.</h1><p>Lihat penugasan mingguan dan hari libur masing-masing karyawan.</p></div><span class="data-label">Roster contoh · RST-OCT-V1</span></div>
    @include('attendance._navigation', ['page' => 'schedules'])
    <section class="directory-panel" aria-labelledby="schedule-title">
        <div class="section-heading directory-heading"><div><h2 id="schedule-title">{{ $weekLabel }}</h2><p>Shift contoh terpublikasi · Asia/Jakarta (WIB)</p></div><span class="data-label">Baca saja</span></div>
        @include('attendance._filters', ['schedule' => true])
        <div class="week-navigation" aria-label="Pilih minggu">
            @if($filters['week'] === '2026-10-19')<a href="{{ route('prototype.schedules', array_merge($filters, ['week' => '2026-10-12'])) }}"><x-icon class="arrow-back" name="arrow" size="16"/>Minggu sebelumnya</a>@else<span aria-disabled="true">Awal cuplikan</span>@endif
            <p>{{ $people->count() }} karyawan sesuai filter</p>
            @if($filters['week'] === '2026-10-12')<a href="{{ route('prototype.schedules', array_merge($filters, ['week' => '2026-10-19'])) }}">Minggu berikutnya<x-icon name="arrow" size="16"/></a>@else<span aria-disabled="true">Akhir cuplikan</span>@endif
        </div>
        @if($people->isEmpty())
            <div class="empty-state"><x-icon name="calendar" size="28"/><h3>Tidak ada jadwal untuk kombinasi filter ini</h3><p>Coba semua karyawan atau pilih cabang yang sesuai.</p><a class="secondary-button button-link" href="{{ route('prototype.schedules') }}">Hapus filter jadwal</a></div>
        @else
            <div class="people-table-wrap" role="region" aria-label="Jadwal mingguan, dapat digeser horizontal" tabindex="0"><table class="roster-table"><caption class="sr-only">Roster sintetis {{ $weekLabel }}</caption><thead><tr><th scope="col">Karyawan</th>@foreach($days as $day)<th scope="col">{{ $day->locale('id')->translatedFormat('D') }}<span>{{ $day->format('d') }} Okt</span></th>@endforeach</tr></thead><tbody>
                @foreach($people as $person)
                    <tr><th scope="row"><a href="{{ route('prototype.employees.show', ['employee' => $person['id']]) }}">{{ $person['name'] }}</a><span>{{ $person['branch'] }}</span></th>
                        @foreach($days as $day)
                            @php($shift = $records['ATT-'.$person['id'].'-'.$day->day])
                            <td><a class="roster-shift {{ $shift['off'] ? 'roster-off' : '' }}" href="{{ route('prototype.attendance.show', array_merge($filters, ['record' => $shift['id'], 'from' => 'schedules'])) }}" aria-label="{{ $person['name'] }}, {{ $shift['date_label'] }}, {{ $shift['off'] ? 'libur terjadwal' : $shift['shift'].' '.$shift['start']->format('H.i').' sampai '.$shift['end']->format('H.i').($shift['overnight'] ? ' hari berikutnya' : '') }}">
                                <strong>{{ $shift['off'] ? 'Libur' : $shift['shift'] }}</strong>@if(!$shift['off'])<span>{{ $shift['start']->format('H.i') }}–{{ $shift['end']->format('H.i') }}</span>@if($shift['overnight'])<small>Hari berikutnya</small>@endif @else<span>Tidak ada shift</span>@endif
                            </a></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
        <p class="directory-footnote"><x-icon name="info" size="15"/>Tanggal kolom adalah tanggal mulai shift. Malam 19 Oktober pukul 22.00 sampai 20 Oktober pukul 06.00 tetap satu hari kerja 19 Oktober. Hari libur mengikuti roster tiap karyawan.</p>
    </section>
</x-app-layout>
