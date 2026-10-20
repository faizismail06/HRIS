<x-app-layout title="Struktur organisasi" active="organization">
    <div class="page-heading"><div><h1>Struktur organisasi</h1><p>Lihat hubungan unit, penanggung jawab dan anggota tim.</p></div><a class="secondary-button button-link" href="{{ route('prototype.employees') }}">Direktori karyawan <x-icon name="arrow" size="16"/></a></div>
    <section class="organization-panel" aria-labelledby="company-title">
        <div class="organization-heading"><div><h2 id="company-title">Contoh perusahaan</h2><p>Satu entitas · 6 unit organisasi · 3 cabang contoh</p></div><form method="get" action="{{ route('prototype.organization') }}" class="organization-filter"><label><span>Cabang anggota tim</span><select name="branch"><option value="">Semua cabang</option>@foreach(['Jakarta', 'Bandung', 'Surabaya'] as $location)<option @selected($branch === $location)>{{ $location }}</option>@endforeach</select></label><button type="submit" class="secondary-button">Terapkan</button></form></div>
        <p class="organization-summary">{{ $working->count() }} karyawan bekerja dalam cuplikan{{ $branch ? ' cabang '.$branch : '' }}. Termasuk status Aktif dan Offboarding; tidak termasuk Pra-bergabung atau Berakhir.</p>
        <div class="org-root"><x-icon name="building" size="24"/><div><strong>Direksi</strong><a href="{{ route('prototype.employees.show', ['employee' => 'EMP-001']) }}">Ratna Putri · Direktur</a></div><span class="data-label">Acuan 20 Oktober 2026</span></div>
        <ul class="department-list" aria-label="Unit di bawah Direksi">
            @foreach($departments as $key => $department)
                @continue($key === 'direksi')
                @php($members = $working->where('department', $key))
                <li><details class="department-detail"><summary><span><strong>{{ $department['name'] }}</strong><small>Penanggung jawab: {{ $leads[$department['lead']]['name'] }}</small></span><span class="department-count">{{ $members->count() }} bekerja</span><x-icon name="chevron" size="17"/></summary><div class="department-content">
                    <div class="department-context"><p>Unit induk: Direksi</p><a href="{{ route('prototype.employees', ['department' => $key, 'branch' => $branch]) }}">Lihat semua profil unit <x-icon name="arrow" size="15"/></a></div>
                    @if($members->isEmpty())<p class="team-empty">Belum ada anggota bekerja untuk unit dan cabang ini dalam cuplikan. Profil pra-bergabung atau berakhir dapat dilihat di direktori.</p>@else<ul class="team-list">@foreach($members as $member)<li><a class="person-link" href="{{ route('prototype.employees.show', ['employee' => $member['id']]) }}"><span class="avatar" aria-hidden="true">{{ $member['initials'] }}</span><span><strong>{{ $member['name'] }}</strong><small>{{ $member['job'] }} · {{ $member['branch'] }}</small></span></a></li>@endforeach</ul>@endif
                </div></details></li>
            @endforeach
        </ul>
        <p class="directory-footnote"><x-icon name="info" size="15"/>Filter cabang mempersempit anggota, bukan hubungan induk unit atau penanggung jawab perusahaan. Struktur ini tidak menetapkan izin akses.</p>
    </section>
</x-app-layout>
