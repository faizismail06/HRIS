<x-app-layout title="Kalender ketersediaan tim" active="requests">
    <div class="page-heading"><div><h1>Kalender ketersediaan tim</h1><p>Rencana roster dan ketidaktersediaan yang sudah disetujui, tanpa alasan pribadi.</p></div><span class="data-label">Acuan 20 Oktober 2026</span></div>
    @include('requests._navigation', ['page' => 'calendar'])
    <section class="directory-panel" aria-labelledby="calendar-title"><div class="section-heading directory-heading"><div><h2 id="calendar-title">{{ $days->first()->locale('id')->translatedFormat('j M') }} – {{ $days->last()->locale('id')->translatedFormat('j M Y') }}</h2><p>Lima profil sintetis · tanggal mengikuti awal shift · WIB</p></div></div>
        <form method="get" action="{{ route('prototype.team-calendar') }}" class="directory-filters attendance-filters">
            <label><span>Minggu kerja</span><select name="week">@foreach(['2026-10-12' => '12–18 Oktober 2026', '2026-10-19' => '19–25 Oktober 2026'] as $key => $label)<option value="{{ $key }}" @selected($filters['week'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Cabang</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $branch)<option @selected($filters['branch'] === $branch)>{{ $branch }}</option>@endforeach</select></label>
            <label><span>Karyawan</span><select name="employee"><option value="">Semua karyawan contoh</option>@foreach($employees as $employee)<option value="{{ $employee['id'] }}" @selected($filters['employee'] === $employee['id'])>{{ $employee['name'] }}</option>@endforeach</select></label><button class="primary-button" type="submit">Terapkan</button>
        </form>
        <p class="calendar-privacy"><x-icon name="info" size="16"/>Pengajuan yang belum final tidak mengubah ketersediaan. Tidak tersedia bukan informasi tentang alasan cuti atau diagnosis.</p>
        @if($people->isEmpty())<div class="empty-state"><h3>Tidak ada anggota yang cocok</h3><p>Perluas cabang atau pilihan karyawan.</p><a class="secondary-button button-link" href="{{ route('prototype.team-calendar') }}">Hapus filter</a></div>@else
            <div class="people-table-wrap" role="region" aria-label="Kalender ketersediaan, dapat digeser horizontal" tabindex="0"><table class="roster-table availability-table"><caption class="sr-only">Ketersediaan sintetis, bukan bukti kehadiran aktual</caption><thead><tr><th scope="col">Karyawan</th>@foreach($days as $day)<th scope="col">{{ $day->locale('id')->translatedFormat('D') }}<span>{{ $day->format('d') }} Okt</span></th>@endforeach</tr></thead><tbody>
                @foreach($people as $person)<tr><th scope="row">{{ $person['name'] }}<span>{{ $person['branch'] }}</span></th>@foreach($days as $day)@php($state = $availability['ATT-'.$person['id'].'-'.$day->day])<td><span class="availability-state availability-{{ strtolower($state) }}">{{ ['WORK' => 'Terjadwal', 'UNAVAILABLE' => 'Tidak tersedia', 'DAY_OFF' => 'Libur'][$state] }}</span></td>@endforeach</tr>@endforeach
            </tbody><tfoot><tr><th scope="row">Shift terjadwal</th>@foreach($days as $day)<td>{{ $people->filter(fn ($person) => $availability['ATT-'.$person['id'].'-'.$day->day] === 'WORK')->count() }} shift</td>@endforeach</tr></tfoot></table></div>
        @endif
        <p class="directory-footnote">Kalender ini bukan rekap clock-in/out atau perhitungan kebutuhan staf per jam. Pengajuan tertunda, jenis cuti pribadi, alasan dan lampiran tidak dikirim ke tampilan kalender.</p>
    </section>
</x-app-layout>
