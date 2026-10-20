<x-app-layout :title="$spec['title']" active="screens"><a class="back-link" href="{{ route('prototype.screens') }}"><x-icon name="arrow" size="16"/>Kembali ke panduan layar</a><div class="page-heading"><div><h1>{{ $spec['title'] }}</h1><p>Contoh state antarmuka · tidak ada operasi yang sedang berjalan.</p></div><span class="data-label">Statis</span></div><section class="profile-section state-preview" aria-labelledby="state-context-title"><h2 id="state-context-title">{{ in_array($state, ['forbidden', 'expired']) ? 'Lindungi akses catatan' : 'Konteks dan langkah berikutnya' }}</h2><p class="request-description">{{ $spec['text'] }}</p>
    @if($state === 'loading')<div class="static-skeleton" aria-hidden="true"><span></span><span></span><span></span></div>@endif
    @if($state === 'confirmation')<div class="static-field static-confirmation"><label for="confirm-scope">Ketik kode skenario · contoh dinonaktifkan</label><input id="confirm-scope" type="text" value="SCENARIO-DEMO-001" disabled><div class="static-actions"><button type="button" class="primary-button" disabled>Konfirmasi reset · belum aktif</button></div></div>@endif
    @if($retained)
        <dl class="profile-fields">
            @foreach($retained as $field)
                <div><dt>{{ $field['label'] }} · input contoh dipertahankan</dt><dd>{{ $field['value'] }}</dd></div>
            @endforeach
            <div><dt>Pengiriman</dt><dd>Belum ada perubahan tersimpan</dd></div>
        </dl>
    @endif
    @if($state === 'stale')<dl class="profile-fields"><div><dt>Versi yang dibuka</dt><dd>V1</dd></div><div><dt>Versi sumber kini</dt><dd>V2 · keputusan lama belum dikirim</dd></div></dl>@endif
    <div class="employment-links"><a class="secondary-button button-link" href="{{ route('prototype.screen', ['screen' => $spec['screen']]) }}">{{ $spec['action'] }}</a>@if($state === 'permission-denied')<a class="inline-link" href="{{ route('prototype.attendance.show', ['record' => 'ATT-EMP-004-18']) }}">Lihat shift belum lengkap</a>@endif</div>
</section></x-app-layout>
