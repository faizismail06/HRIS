<x-app-layout :title="$definition['title']" active="contracts">
    <div class="page-heading"><div><h1>{{ $definition['title'] }}</h1><p>{{ $definition['description'] }}</p></div><span class="snapshot">Snapshot contoh<br>20 Oktober 2026 · 08.45 WIB</span></div>
    @include('employment._navigation')
    <section class="directory-panel" aria-labelledby="employment-list-title">
        <div class="section-heading directory-heading"><div><h2 id="employment-list-title">{{ $kind === 'contracts' ? 'Periode dan versi kontrak' : ($kind === 'letters' ? 'Register surat contoh' : 'Riwayat dan rencana perubahan') }}</h2><p>Tanggal dan status dibaca pada snapshot, termasuk arsip dan rancangan.</p></div><span class="data-label">Baca saja</span></div>
        <form method="get" action="{{ route('prototype.'.$kind) }}" class="directory-filters request-filters">
            <label class="directory-search"><span>Referensi atau karyawan</span><span class="search-field"><x-icon name="search" size="18"/><input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Cari nomor, judul, nama…" maxlength="100"></span></label>
            <label><span>Status</span><select name="status"><option value="">Semua status</option>@foreach($definition['states'] as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Cabang profil saat ini</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $branch)<option @selected($filters['branch'] === $branch)>{{ $branch }}</option>@endforeach</select></label>
            <label><span>Karyawan</span><select name="employee"><option value="">Semua karyawan contoh</option>@foreach($employees as $person)<option value="{{ $person['id'] }}" @selected($filters['employee'] === $person['id'])>{{ $person['name'] }}</option>@endforeach</select></label>
            <button class="primary-button" type="submit">Terapkan</button>
        </form>
        <div class="directory-results"><p role="status">{{ $items->count() }} catatan sesuai filter</p><a href="{{ route('prototype.'.$kind) }}">Hapus filter</a></div>
        @if($items->isEmpty())
            <div class="empty-state"><x-icon name="search" size="28"/><h3>Tidak ada catatan yang cocok</h3><p>Coba referensi lain atau perluas status, cabang dan karyawan.</p><a class="secondary-button button-link" href="{{ route('prototype.'.$kind) }}">Tampilkan semua catatan</a></div>
        @else
            <div class="people-table-wrap" role="region" aria-label="Daftar administrasi, dapat digeser horizontal" tabindex="0"><table class="people-table"><caption class="sr-only">{{ $definition['title'] }} sintetis sesuai filter</caption><thead><tr><th scope="col">Referensi / Nomor</th><th scope="col">Karyawan / Catatan</th><th scope="col">Status</th><th scope="col">{{ $kind === 'letters' ? 'Tanggal terbit / Persiapan draf' : 'Periode / Tanggal efektif' }}</th><th scope="col"><span class="sr-only">Detail</span></th></tr></thead><tbody>
                @foreach($items as $item)
                    <tr><th scope="row"><a class="request-reference" href="{{ route('prototype.'.$kind.'.show', array_merge($filters, ['record' => $item['id']])) }}">{{ $item['id'] }}</a><span class="cell-meta">{{ $item['number'] ?? 'Belum ada nomor final' }}</span></th><td><span class="cell-title">{{ $item['person']['name'] }}</span><small>{{ $item['title'] }}</small></td><td>@include('employment._state')</td><td>{{ \App\Support\PrototypePeople::date($item['start']) }}@isset($item['end'])<small>sampai {{ \App\Support\PrototypePeople::date($item['end']) }}</small>@endisset @if($item['status'] === 'DRAFT')<small>Persiapan draf · belum diterbitkan</small>@elseif($item['status'] === 'IN_REVIEW')<small>Tanggal usulan · belum final</small>@endif</td><td><a class="table-detail-link" aria-label="Detail {{ $item['id'] }}" href="{{ route('prototype.'.$kind.'.show', array_merge($filters, ['record' => $item['id']])) }}"><x-icon name="chevron" size="17"/></a></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
        <p class="directory-footnote"><x-icon name="info" size="15"/>Cabang mengikuti profil saat ini, bukan snapshot dokumen. Tidak ada unggahan, penerbitan surat atau perubahan data dari pratinjau ini.</p>
    </section>
</x-app-layout>
