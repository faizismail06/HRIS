<x-app-layout title="Daftar pengajuan" active="requests">
    <div class="page-heading"><div><h1>Pengajuan dan tindak lanjut</h1><p>Lihat jenis permintaan, pemilik tahap dan progres keputusan.</p></div><span class="snapshot">Snapshot contoh<br>20 Oktober 2026 · 08.45 WIB</span></div>
    @include('requests._navigation', ['page' => 'requests'])
    <section class="directory-panel" aria-labelledby="request-list-title">
        <div class="section-heading directory-heading"><div><h2 id="request-list-title">Daftar pengajuan</h2><p>8 contoh cuti, koreksi absensi, lembur dan tukar shift</p></div><span class="data-label">Baca saja</span></div>
        <form method="get" action="{{ route('prototype.requests') }}" class="directory-filters request-filters">
            <label class="directory-search"><span>Nomor atau karyawan</span><span class="search-field"><x-icon name="search" size="18"/><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Cari pengajuan…" maxlength="100"></span></label>
            <label><span>Jenis</span><select name="type"><option value="">Semua jenis</option>@foreach($types as $key => $label)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Status</span><select name="status"><option value="">Semua status</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Cabang</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $branch)<option @selected($filters['branch'] === $branch)>{{ $branch }}</option>@endforeach</select></label>
            <label><span>Karyawan</span><select name="employee"><option value="">Semua karyawan contoh</option>@foreach($employees as $person)<option value="{{ $person['id'] }}" @selected($filters['employee'] === $person['id'])>{{ $person['name'] }}</option>@endforeach</select></label>
            <button class="primary-button" type="submit">Terapkan</button>
        </form>
        <div class="directory-results"><p role="status">{{ $items->count() }} pengajuan sesuai filter · terbaru lebih dulu</p><a href="{{ route('prototype.requests') }}">Hapus filter</a></div>
        @if($items->isEmpty())
            <div class="empty-state"><x-icon name="search" size="28"/><h3>Tidak ada pengajuan yang cocok</h3><p>Coba nomor lain atau perluas jenis, status dan cabang.</p><a class="secondary-button button-link" href="{{ route('prototype.requests') }}">Tampilkan semua pengajuan</a></div>
        @else
            <div class="people-table-wrap" role="region" aria-label="Daftar pengajuan, dapat digeser horizontal" tabindex="0"><table class="people-table request-table"><caption class="sr-only">Pengajuan sintetis sesuai filter</caption><thead><tr><th scope="col">Pengajuan</th><th scope="col">Karyawan / Periode</th><th scope="col">Status</th><th scope="col">Pemilik tahap aktif</th><th scope="col">Sejak diajukan</th><th scope="col"><span class="sr-only">Detail</span></th></tr></thead><tbody>
                @foreach($items as $item)
                    <tr><th scope="row"><a class="request-reference" href="{{ route('prototype.requests.show', array_merge($filters, ['requestId' => $item['id'], 'from' => 'requests'])) }}">{{ $item['id'] }}</a><span class="cell-meta">{{ $item['type_label'] }}</span></th><td><span class="cell-title">{{ $item['person']['name'] }} · {{ $item['person']['branch'] }}</span><small>{{ $item['period'] }}</small></td><td><span class="employment-state request-state-{{ strtolower($item['status']) }}">{{ $item['status_label'] }}</span></td><td>@if($item['active_step'])<span class="cell-title">{{ $item['active_step']['actor'] }}</span><small>{{ $item['active_step']['role'] }}</small>@else<span class="cell-meta">Tidak ada tahap aktif</span>@endif</td><td>{{ $item['age'] }}</td><td><a class="table-detail-link" aria-label="Detail {{ $item['id'] }}" href="{{ route('prototype.requests.show', array_merge($filters, ['requestId' => $item['id'], 'from' => 'requests'])) }}"><x-icon name="chevron" size="17"/></a></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
        <p class="directory-footnote"><x-icon name="info" size="15"/>Usia dihitung sejak pengajuan sampai snapshot, bukan lama menunggu tiap approver. Reminder atau eskalasi tidak berarti persetujuan otomatis.</p>
    </section>
</x-app-layout>
