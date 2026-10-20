<x-app-layout title="Rekap kehadiran" active="attendance">
    <div class="page-heading"><div><h1>Jadwal jelas, kehadiran tertata.</h1><p>Telusuri hasil per tanggal kerja, termasuk shift yang melewati tengah malam.</p></div><span class="snapshot">Snapshot contoh<br>20 Oktober 2026 · 08.45 WIB</span></div>
    @include('attendance._navigation', ['page' => 'attendance'])
    <section class="directory-panel" aria-labelledby="attendance-list-title">
        <div class="section-heading directory-heading"><div><h2 id="attendance-list-title">Rekap · {{ $dateLabel }}</h2><p>5 karyawan dalam cuplikan · seluruh waktu Asia/Jakarta (WIB)</p></div><span class="data-label">SIMULATED</span></div>
        @include('attendance._filters', ['schedule' => false])
        <div class="attendance-summary" aria-label="Ringkasan sebelum filter status">
            <span>Dalam tanggal, karyawan dan cabang terpilih:</span>
            @foreach($counts as $key => $count)<span><strong>{{ $count }}</strong> {{ $statuses[$key] }}</span>@endforeach
            @if($counts->isEmpty())<span>Belum ada baris yang cocok.</span>@endif
        </div>
        <div class="directory-results"><p role="status">{{ $records->count() }} baris sesuai seluruh filter</p><a href="{{ route('prototype.attendance') }}">Hapus filter</a></div>
        @if($records->isEmpty())
            <div class="empty-state"><x-icon name="calendar" size="28"/><h3>Tidak ada hasil kehadiran yang cocok</h3><p>Perluas cabang, karyawan atau status. Cuplikan tersedia 12–25 Oktober 2026.</p><a class="secondary-button button-link" href="{{ route('prototype.attendance') }}">Tampilkan rekap contoh</a></div>
        @else
            <div class="people-table-wrap" role="region" aria-label="Tabel kehadiran, dapat digeser horizontal" tabindex="0"><table class="people-table attendance-table"><caption class="sr-only">Hasil kehadiran sintetis pada tanggal kerja {{ $filters['date'] }}</caption><thead><tr><th scope="col">Karyawan</th><th scope="col">Shift terjadwal</th><th scope="col">Clock-in</th><th scope="col">Clock-out</th><th scope="col">Status</th><th scope="col">Atribut</th><th scope="col"><span class="sr-only">Detail shift</span></th></tr></thead><tbody>
                @foreach($records as $record)
                    <tr><th scope="row"><span class="cell-title">{{ $record['person']['name'] }}</span><span class="cell-meta">{{ $record['person']['branch'] }}</span></th><td>@if($record['off'])Libur terjadwal @else<span class="cell-title">{{ $record['shift'] }} · {{ $record['start']->format('H.i') }}–{{ $record['end']->format('H.i') }}</span>@if($record['overnight'])<small>Pulang hari berikutnya</small>@endif @endif</td><td>{{ \App\Support\PrototypeAttendance::time($record['in']) }}</td><td>{{ \App\Support\PrototypeAttendance::time($record['out']) }}</td><td><span class="employment-state attendance-state-{{ strtolower($record['status']) }}">{{ $record['status_label'] }}</span></td><td>@if($record['late'])<span class="attendance-flag">Terlambat {{ $record['late'] }} mnt</span>@endif @if($record['early'])<span class="attendance-flag">Pulang awal {{ $record['early'] }} mnt</span>@endif @if(!$record['late'] && !$record['early'])<span class="cell-meta">—</span>@endif</td><td><a class="table-detail-link" href="{{ route('prototype.attendance.show', array_merge($filters, ['record' => $record['id'], 'from' => 'attendance'])) }}" aria-label="Detail shift {{ $record['person']['name'] }}"><x-icon name="chevron" size="17"/></a></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
        <p class="directory-footnote"><x-icon name="info" size="15"/>Terlambat dan pulang awal adalah atribut terpisah dari Hadir. Belum lengkap, cuti, libur dan tidak hadir merupakan status yang berbeda. Ini hasil skenario sintetis, bukan absensi terverifikasi.</p>
    </section>
</x-app-layout>
