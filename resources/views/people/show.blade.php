<x-app-layout :title="$person['name']" active="employees">
    <a class="back-link" href="{{ route('prototype.employees', $filters) }}"><x-icon name="arrow" size="16"/>Kembali ke direktori</a>
    <section class="profile-heading" aria-labelledby="profile-title"><span class="avatar profile-avatar" aria-hidden="true">{{ $person['initials'] }}</span><div><h1 id="profile-title">{{ $person['name'] }}</h1><p>{{ $person['job'] }}<span class="copy-separator">·</span>{{ $person['department_name'] }}</p><span class="profile-reference">{{ $person['id'] }}<span class="copy-separator">·</span>{{ $person['branch'] }}</span></div><span class="employment-state state-{{ strtolower($person['status']) }}">{{ $person['status_label'] }}</span></section>
    <nav class="section-links" aria-label="Bagian profil"><a href="#penugasan">Penugasan</a><a href="#riwayat">Riwayat kerja</a><a href="#tim">Hubungan tim</a></nav>

    @if($person['status'] === 'PREBOARDING')
        <p class="profile-notice"><x-icon name="calendar" size="17"/>Mulai bekerja terjadwal {{ $person['joined_label'] }}. Profil ini belum berstatus aktif pada tanggal acuan.</p>
    @elseif($person['status'] === 'ENDED')
        <p class="profile-notice"><x-icon name="info" size="17"/>Periode kerja berakhir {{ \App\Support\PrototypePeople::date($person['end']) }}. Profil dipertahankan untuk riwayat.</p>
    @elseif($person['status'] === 'OFFBOARDING')
        <p class="profile-notice"><x-icon name="calendar" size="17"/>Dalam proses offboarding; akhir periode kerja contoh {{ \App\Support\PrototypePeople::date($person['end']) }}.</p>
    @endif

    <div class="profile-grid">
        <section id="penugasan" class="profile-section" aria-labelledby="assignment-title"><div class="section-heading"><div><h2 id="assignment-title">Identitas dan penugasan</h2><p>Snapshot contoh per 20 Oktober 2026</p></div><span class="data-label">Data sintetis</span></div><dl class="profile-fields">
            <div><dt>Nomor karyawan</dt><dd>{{ $person['id'] }}</dd></div><div><dt>Email kerja contoh</dt><dd class="break-text">{{ $person['email'] }}</dd></div><div><dt>Jabatan</dt><dd>{{ $person['job'] }}</dd></div><div><dt>Departemen</dt><dd><a href="{{ route('prototype.organization', ['branch' => $person['branch']]) }}">{{ $person['department_name'] }}</a></dd></div><div><dt>Cabang</dt><dd>{{ $person['branch'] }}</dd></div><div><dt>Zona waktu kerja</dt><dd>Asia/Jakarta · WIB</dd></div><div><dt>{{ $person['status'] === 'PREBOARDING' ? 'Rencana mulai bekerja' : 'Tanggal bergabung' }}</dt><dd><time datetime="{{ $person['joined'] }}">{{ $person['joined_label'] }}</time></dd></div><div><dt>Atasan langsung</dt><dd>@if($manager)<a href="{{ route('prototype.employees.show', array_merge(['employee' => $manager['id']], $filters)) }}">{{ $manager['name'] }}</a>@else
                Tidak ada · pimpinan perusahaan
            @endif</dd></div>
        </dl><p class="directory-footnote">Nomor karyawan bukan nomor identitas nasional. Data identitas pribadi, bank, pajak dan medis tidak disertakan.</p></section>

        <section id="riwayat" class="profile-section" aria-labelledby="history-title"><h2 id="history-title">Riwayat kerja</h2><ol class="history-list">
            <li><span class="history-dot"></span><div><time datetime="{{ $person['joined'] }}">{{ $person['joined_label'] }}</time><h3>{{ $person['status'] === 'PREBOARDING' ? 'Rencana bergabung' : 'Mulai periode kerja' }}</h3><p>{{ $person['job'] }} · {{ $person['branch'] }}</p></div></li>
            @if($person['id'] === 'EMP-004')
                <li class="history-future"><span class="history-dot"></span><div><time datetime="2026-11-01">1 November 2026 · terjadwal</time><h3>Rencana perubahan penugasan</h3><p>Supervisor Operasional · Jakarta. Belum menggantikan jabatan saat ini.</p></div></li>
            @endif
            @if($person['end'])
                <li class="{{ $person['status'] === 'OFFBOARDING' ? 'history-future' : '' }}"><span class="history-dot"></span><div><time datetime="{{ $person['end'] }}">{{ \App\Support\PrototypePeople::date($person['end']) }}</time><h3>{{ $person['status'] === 'ENDED' ? 'Periode kerja berakhir' : 'Rencana akhir periode kerja' }}</h3><p>Riwayat dan penugasan terdahulu tetap dipertahankan.</p></div></li>
            @endif
        </ol><p class="history-note">Timeline contoh, bukan audit transaksi atau hasil workflow persetujuan.</p></section>
    </div>

    <section id="tim" class="profile-section team-section" aria-labelledby="team-title"><div class="section-heading"><div><h2 id="team-title">Hubungan tim</h2><p>Laporan langsung yang berstatus Aktif atau Offboarding pada tanggal acuan.</p></div><a class="inline-link" href="{{ route('prototype.organization') }}">Lihat struktur <x-icon name="arrow" size="16"/></a></div>
        @if($reports->isEmpty())<p class="team-empty">Tidak ada laporan langsung dalam cuplikan profil ini.</p>@else<ul class="team-list">@foreach($reports as $report)<li><a class="person-link" href="{{ route('prototype.employees.show', array_merge(['employee' => $report['id']], $filters)) }}"><span class="avatar" aria-hidden="true">{{ $report['initials'] }}</span><span><strong>{{ $report['name'] }}</strong><small>{{ $report['job'] }} · {{ $report['branch'] }}</small></span></a></li>@endforeach</ul>@endif
    </section>
</x-app-layout>
