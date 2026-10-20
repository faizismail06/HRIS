<x-app-layout :title="$item['id']" active="contracts">
    <a class="back-link" href="{{ route('prototype.'.$kind, $filters) }}"><x-icon name="arrow" size="16"/>Kembali ke {{ mb_strtolower($definition['title']) }}</a>
    <div class="page-heading"><div><h1>{{ $item['title'] }}</h1><p>{{ $item['id'] }}<span class="copy-separator">·</span>{{ $item['person']['name'] }}</p></div>@include('employment._state')</div>
    @include('employment._navigation')
    <section class="profile-section" aria-labelledby="employment-summary-title">
        <div class="section-heading"><h2 id="employment-summary-title">Ringkasan catatan</h2><span class="data-label">Snapshot 20 Oktober 2026</span></div>
        <dl class="profile-fields">
            <div><dt>Nomor contoh</dt><dd>{{ $item['number'] ?? 'Belum ada nomor final' }}</dd></div><div><dt>Versi template contoh</dt><dd>{{ $item['version'] }}</dd></div>
            <div><dt>{{ $kind === 'letters' ? ($item['status'] === 'DRAFT' ? 'Draf disiapkan' : 'Diterbitkan') : ($item['status'] === 'IN_REVIEW' ? 'Usulan mulai berlaku' : 'Mulai berlaku') }}</dt><dd>{{ \App\Support\PrototypePeople::date($item['start']) }}</dd></div>
            @isset($item['end'])<div><dt>Akhir periode kontrak</dt><dd>{{ \App\Support\PrototypePeople::date($item['end']) }}</dd></div>@endisset
            @isset($item['job'])<div><dt>Jabatan dalam versi ini</dt><dd>{{ $item['job'] }}</dd></div>@endisset
        </dl>
        <p class="request-description">{{ $item['note'] }}</p>
        <div class="employment-links"><a class="inline-link" href="{{ route('prototype.employees.show', ['employee' => $item['employee']]) }}">Lihat profil saat ini <x-icon name="arrow" size="16"/></a>
            @isset($item['related'])
                @php($relatedFilters = $item['related']['kind'] === $kind ? $filters : array_intersect_key($filters, array_flip(['branch', 'employee'])))
                <a class="inline-link" href="{{ route('prototype.'.$item['related']['kind'].'.show', array_merge($relatedFilters, ['record' => $item['related']['id']])) }}">{{ $item['related']['label'] }} <x-icon name="arrow" size="16"/></a>
            @endisset
        </div>
    </section>
    @if($kind === 'contracts')
        <section class="profile-section team-section" aria-labelledby="contract-reminders-title"><h2 id="contract-reminders-title">Tinjauan menjelang akhir kontrak</h2>
            @if($reminders)
                <p class="history-note">Tanggal pengingat contoh, dihitung dari akhir periode. Label waktu mengacu ke 20 Oktober 2026.</p>
                <ul class="history-list">@foreach($reminders as $reminder)<li><span class="history-dot" aria-hidden="true"></span><div><h3>{{ $reminder['days'] }} hari sebelum berakhir</h3><p>{{ \App\Support\PrototypePeople::date($reminder['date']->toDateString()) }} · {{ $reminder['past'] ? 'Tanggal contoh terlewati' : 'Mendatang' }}</p></div></li>@endforeach</ul>
            @else
                <p class="history-note">Pratinjau tanggal pengingat hanya ditampilkan untuk kontrak aktif. Arsip dan rancangan tetap memiliki statusnya sendiri.</p>
            @endif
            <p class="request-description">Alur yang direncanakan: atasan memberi rekomendasi, HR meninjau lalu memutuskan tindak lanjut. Halaman ini tidak mengirim pengingat, memperpanjang kontrak, mengakhiri hubungan kerja atau mencabut akses otomatis.</p>
        </section>
    @elseif($kind === 'career-changes')
        <section class="directory-panel team-section" aria-labelledby="career-diff-title"><div class="directory-heading"><h2 id="career-diff-title">Sebelum dan sesudah keputusan</h2><p class="history-note">{{ $item['status'] === 'EFFECTIVE' ? 'Riwayat yang sudah efektif pada snapshot.' : 'Nilai sesudah masih rencana atau usulan; profil hari ini tetap berlaku.' }}</p></div>
            <div class="people-table-wrap" role="region" aria-label="Perbandingan perubahan karier, dapat digeser horizontal" tabindex="0"><table class="people-table"><caption class="sr-only">Perubahan {{ $item['id'] }}</caption><thead><tr><th scope="col">Penugasan</th><th scope="col">Sebelum</th><th scope="col">Sesudah{{ $item['status'] === 'IN_REVIEW' ? ' · usulan' : '' }}</th></tr></thead><tbody>@foreach($item['changes'] as $change)<tr><th scope="row">{{ $change['field'] }}</th><td>{{ $change['before'] }}</td><td>{{ $change['after'] }}</td></tr>@endforeach</tbody></table></div>
            <p class="directory-footnote"><x-icon name="info" size="15"/>Penyesuaian retroaktif pada periode tertutup perlu catatan penyesuaian terpisah. Mesin tanggal efektif dan penguncian periode belum diimplementasikan.</p>
        </section>
    @else
        <section class="profile-section team-section" aria-labelledby="letter-preview-title"><h2 id="letter-preview-title">Pratinjau isi surat</h2><p class="history-note">Isi di bawah memakai snapshot versi ini, bukan data profil yang berubah kemudian. Hanya pratinjau HTML; bukan dokumen resmi.</p>
            <article class="letter-preview" aria-label="Snapshot surat sintetis {{ $item['id'] }}">
                <p class="letter-stamp">CONTOH · DATA SINTETIS{{ $item['status'] === 'DRAFT' ? ' · BELUM DITERBITKAN' : '' }}</p>
                <h3>{{ $item['title'] }}</h3><p>{{ $item['number'] ?? 'Belum ada nomor final' }}</p>
                <dl class="profile-fields">
                    <div><dt>Nama dalam snapshot</dt><dd>{{ $item['snapshot']['name'] }}</dd></div><div><dt>ID karyawan contoh</dt><dd>{{ $item['snapshot']['employee'] }}</dd></div>
                    <div><dt>Jabatan dalam snapshot</dt><dd>{{ $item['snapshot']['job'] }}</dd></div><div><dt>Unit dalam snapshot</dt><dd>{{ $item['snapshot']['department'] }}</dd></div>
                    <div><dt>Cabang dalam snapshot</dt><dd>{{ $item['snapshot']['branch'] }}</dd></div><div><dt>Periode dalam snapshot</dt><dd>{{ $item['snapshot']['period'] }}</dd></div>
                </dl><p class="letter-disclaimer">Template {{ $item['version'] }}. Tidak memiliki tanda tangan atau pengesahan. Pratinjau ini tidak menerbitkan, menomori atau menyediakan unduhan dokumen.</p>
            </article>
        </section>
    @endif
</x-app-layout>
