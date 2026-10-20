<x-app-layout title="Direktori karyawan" active="employees">
    <div class="page-heading"><div><h1>Kenali tim Anda.</h1><p>Direktori karyawan, penugasan dan riwayat kerja dalam satu tempat.</p></div><a class="secondary-button button-link" href="{{ route('prototype.organization') }}">Lihat organisasi <x-icon name="arrow" size="16"/></a></div>

    <section class="directory-panel" aria-labelledby="directory-title">
        <div class="section-heading directory-heading"><div><h2 id="directory-title">Direktori karyawan <span class="heading-count">{{ $total }}</span></h2><p>Cuplikan 12 profil sintetis · acuan 20 Oktober 2026</p></div><span class="data-label">Baca saja</span></div>
        <nav class="directory-status" aria-label="Status hubungan kerja">
            <a href="{{ route('prototype.employees', array_merge($filters, ['status' => null])) }}" @if(!$filters['status']) aria-current="true" @endif>Semua <span>{{ $total }}</span></a>
            @foreach($statuses as $value => $label)
                <a href="{{ route('prototype.employees', array_merge($filters, ['status' => $value])) }}" @if($filters['status'] === $value) aria-current="true" @endif>{{ $label }} <span>{{ $statusCounts[$value] ?? 0 }}</span></a>
            @endforeach
        </nav>
        <form class="directory-filters" action="{{ route('prototype.employees') }}" method="get">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <label class="directory-search"><span>Nama, nomor atau jabatan</span><span class="search-field"><x-icon name="search" size="18"/><input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Cari karyawan…" autocomplete="off"></span></label>
            <label><span>Departemen</span><select name="department"><option value="">Semua departemen</option>@foreach($departments as $key => $department)<option value="{{ $key }}" @selected($filters['department'] === $key)>{{ $department['name'] }}</option>@endforeach</select></label>
            <label><span>Cabang</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $branch)<option @selected($filters['branch'] === $branch)>{{ $branch }}</option>@endforeach</select></label>
            <label><span>Urutkan</span><select name="sort"><option value="name" @selected($filters['sort'] === 'name')>Nama A–Z</option><option value="name-desc" @selected($filters['sort'] === 'name-desc')>Nama Z–A</option><option value="joined" @selected($filters['sort'] === 'joined')>Tanggal bergabung terbaru</option></select></label>
            <button class="primary-button" type="submit">Terapkan</button>
        </form>
        <div class="directory-results"><p role="status">Menampilkan {{ $employees->count() }} dari {{ $total }} profil contoh</p><a href="{{ route('prototype.employees') }}">Hapus filter</a></div>
        @if($employees->isEmpty())
            <div class="empty-state"><x-icon name="search" size="28"/><h3>Tidak ada karyawan yang cocok</h3><p>Periksa kata pencarian atau perluas status, departemen dan cabang.</p><a class="secondary-button button-link" href="{{ route('prototype.employees') }}">Tampilkan semua karyawan</a></div>
        @else
            <div class="people-table-wrap" role="region" aria-label="Tabel karyawan, dapat digeser secara horizontal" tabindex="0">
                <table class="people-table"><caption class="sr-only">Profil karyawan sintetis sesuai filter</caption><thead><tr><th scope="col">Karyawan</th><th scope="col">Penugasan</th><th scope="col">Cabang</th><th scope="col">Status</th><th scope="col">Bergabung</th><th scope="col"><span class="sr-only">Detail</span></th></tr></thead><tbody>
                    @foreach($employees as $person)
                        <tr><th scope="row"><a class="person-link" href="{{ route('prototype.employees.show', array_merge(['employee' => $person['id']], $filters)) }}"><span class="avatar" aria-hidden="true">{{ $person['initials'] }}</span><span><strong>{{ $person['name'] }}</strong><small>{{ $person['id'] }}</small></span></a></th><td><span class="cell-title">{{ $person['job'] }}</span><small>{{ $person['department_name'] }}</small></td><td>{{ $person['branch'] }}</td><td><span class="employment-state state-{{ strtolower($person['status']) }}">{{ $person['status_label'] }}</span></td><td><time datetime="{{ $person['joined'] }}">{{ $person['joined_label'] }}</time></td><td><a class="table-detail-link" href="{{ route('prototype.employees.show', array_merge(['employee' => $person['id']], $filters)) }}" aria-label="Lihat profil {{ $person['name'] }}"><x-icon name="chevron" size="17"/></a></td></tr>
                    @endforeach
                </tbody></table>
            </div>
        @endif
        <p class="directory-footnote"><x-icon name="info" size="15"/>Status hubungan kerja terpisah dari cuti dan akses akun. Profil berakhir tetap tersimpan untuk riwayat.</p>
    </section>
</x-app-layout>
