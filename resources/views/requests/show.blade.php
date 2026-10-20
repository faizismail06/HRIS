<x-app-layout :title="$item['id']" active="requests">
    <a class="back-link" href="{{ route($backRoute, $backFilters) }}"><x-icon name="arrow" size="16"/>{{ $filters['from'] === 'balances' ? 'Kembali ke saldo cuti' : 'Kembali ke daftar pengajuan' }}</a>
    <div class="page-heading"><div><h1>{{ $item['type_label'] }} · {{ $item['id'] }}</h1><p>{{ $item['person']['name'] }}<span class="copy-separator">·</span>{{ $item['person']['branch'] }}</p></div><span class="employment-state request-state-{{ strtolower($item['status']) }}">{{ $item['status_label'] }}</span></div>
    <div class="profile-grid">
        <section class="profile-section" aria-labelledby="request-title"><h2 id="request-title">Ringkasan pengajuan</h2><dl class="profile-fields">
            <div><dt>Periode yang diajukan</dt><dd>{{ $item['period'] }}</dd></div><div><dt>Diajukan</dt><dd>{{ \App\Support\PrototypePeople::date(substr($item['submitted'], 0, 10)) }} · {{ substr($item['submitted'], 11, 5) }} WIB</dd></div>
            <div><dt>Pemilik tahap aktif</dt><dd>{{ $item['active_step']['actor'] ?? 'Tidak ada tahap aktif' }}</dd></div><div><dt>Tenggat tahap contoh</dt><dd>{{ $item['deadline'] ? \Carbon\CarbonImmutable::parse($item['deadline'])->locale('id')->translatedFormat('j M Y, H.i').' WIB' : 'Tidak ada tenggat aktif' }}</dd></div>
            <div><dt>Sejak diajukan pada snapshot</dt><dd>{{ $item['age'] }}</dd></div><div><dt>Workflow contoh</dt><dd>{{ count($item['steps']) }} tahap · snapshot saat pengajuan</dd></div>
            @if($item['units'] !== null)<div><dt>Durasi cuti contoh</dt><dd>{{ \App\Support\PrototypeRequests::days($item['units']) }} hari kerja</dd></div>@endif
        </dl><p class="request-description">{{ $item['summary'] }}</p>
            @isset($item['decision'])<div class="decision-note"><h3>Alasan keputusan contoh</h3><p>{{ $item['decision'] }}</p></div>@endisset
            @isset($item['source'])<a class="inline-link" href="{{ route('prototype.attendance.show', ['record' => $item['source'], 'date' => $item['dates'][0], 'employee' => $item['employee'], 'branch' => $item['person']['branch']]) }}">Lihat shift sumber <x-icon name="arrow" size="16"/></a>@endisset
        </section>
        <section class="profile-section" aria-labelledby="steps-title"><h2 id="steps-title">Alur persetujuan</h2><ol class="approval-list">
            @foreach($item['steps'] as $step)
                <li class="approval-step step-{{ strtolower($step['state']) }}"><span class="approval-marker" aria-hidden="true">@if($step['state'] === 'DONE')<x-icon name="check" size="15"/>@else{{ $loop->iteration }}@endif</span><div><h3>{{ $step['role'] }}</h3><p>{{ $step['actor'] }}</p><span class="step-label">{{ ['DONE' => 'Selesai', 'ACTIVE' => 'Menunggu keputusan', 'WAITING' => 'Belum aktif', 'REJECTED' => 'Ditolak', 'CANCELLED' => 'Tidak dilanjutkan'][$step['state']] }}</span>@if($step['at'])<time datetime="{{ \Carbon\CarbonImmutable::parse($step['at'], 'Asia/Jakarta')->toIso8601String() }}">{{ \Carbon\CarbonImmutable::parse($step['at'])->locale('id')->translatedFormat('j M, H.i') }} WIB</time>@endif</div></li>
            @endforeach
        </ol><p class="history-note">Tahap berikutnya menunggu tahap sebelumnya. Pemohon tidak boleh menyetujui pengajuannya sendiri. Prototipe tidak menyediakan aksi persetujuan atau perubahan status.</p></section>
    </div>
    @if($ledger)
        <section class="profile-section team-section" aria-labelledby="request-balance-title"><div class="section-heading"><div><h2 id="request-balance-title">Konteks saldo 2026</h2><p>Snapshot saldo contoh pemohon, bukan kalkulasi operasional.</p></div><a class="inline-link" href="{{ route('prototype.leave-balances', ['employee' => $item['employee']]) }}">Lihat ledger <x-icon name="arrow" size="16"/></a></div><p class="balance-inline"><strong>{{ \App\Support\PrototypeRequests::days($ledger['available']) }} hari tersedia</strong><span>{{ \App\Support\PrototypeRequests::days($ledger['reserved']) }} hari sedang direservasi</span><span>{{ \App\Support\PrototypeRequests::days($ledger['used']) }} hari terpakai/disetujui</span></p></section>
    @endif
    <p class="directory-footnote"><x-icon name="info" size="15"/>Semua aktor, waktu dan keputusan adalah fixture sintetis. Tidak ada notifikasi, debit saldo, perubahan roster atau penyesuaian raw event yang dikirim dari halaman ini.</p>
</x-app-layout>
