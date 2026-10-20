<p class="profile-notice">Tampilan statis. Semua kolom dan aksi dinonaktifkan; tidak ada data yang disimpan atau dikirim.</p>
<nav class="section-links attendance-navigation" aria-label="Kondisi formulir"><a href="{{ route('prototype.screen', ['screen' => $screen]) }}" @if($state === 'ready') aria-current="page" @endif>Kondisi awal</a><a href="{{ route('prototype.screen', ['screen' => $screen, 'state' => 'invalid']) }}" @if($state === 'invalid') aria-current="page" @endif>Validasi gagal</a></nav>
@if($state === 'invalid')<div class="form-errors" role="alert"><strong>Contoh perubahan belum dapat dikirim.</strong><p>Isi {{ mb_strtolower($spec['fields'][0][0]) }}. Input contoh lainnya dipertahankan.</p></div>@endif
<section class="profile-section" aria-labelledby="static-form-title"><h2 id="static-form-title">{{ $spec['title'] }}</h2>
    <fieldset disabled class="static-fieldset"><legend class="sr-only">Contoh formulir {{ $spec['title'] }}, seluruh kontrol dinonaktifkan</legend><div class="static-form-grid">
        @foreach($spec['fields'] as $index => $field)
            @php($type = $field[2] ?? 'text')
            @php($value = $state === 'invalid' && $index === 0 ? '' : $field[1])
            <div class="static-field {{ $type === 'textarea' ? 'field-wide' : '' }}"><label for="preview-field-{{ $index }}">{{ $field[0] }}@if($index === 0)<span> · wajib</span>@endif</label>
                @if($type === 'select')<select id="preview-field-{{ $index }}" @if($state === 'invalid' && $index === 0) aria-invalid="true" aria-describedby="preview-error" @endif><option>{{ $value ?: 'Pilih nilai contoh' }}</option></select>
                @elseif($type === 'textarea')<textarea id="preview-field-{{ $index }}" rows="3" @if($state === 'invalid' && $index === 0) aria-invalid="true" aria-describedby="preview-error" @endif>{{ $value }}</textarea>
                @else<input id="preview-field-{{ $index }}" type="{{ $type }}" @if($type === 'number') step="1" @endif @if(!in_array($type, ['file', 'password'])) value="{{ $value }}" @endif @if($state === 'invalid' && $index === 0) aria-invalid="true" aria-describedby="preview-error" @endif autocomplete="off">@endif
                @if($state === 'invalid' && $index === 0)<p id="preview-error" class="static-field-error">Isi {{ mb_strtolower($field[0]) }} sebelum melanjutkan.</p>@endif
                @if($type === 'file')<p class="static-field-help">Contoh area lampiran · tidak menerima berkas.</p>@endif
            </div>
        @endforeach
    </div><div class="static-actions">@if($spec['group'] !== 'Akun')<button type="button" class="secondary-button" disabled>Simpan draf · belum aktif</button>@endif<button type="button" class="primary-button" disabled>{{ match($screen) { 'login' => 'Masuk', 'mfa' => 'Verifikasi', 'recovery' => 'Kirim tautan', 'reset-password' => 'Tetapkan kata sandi', 'invite' => 'Aktifkan akun', 'approval' => 'Kirim keputusan', 'clock' => 'Clock-in contoh', 'roster' => 'Publikasikan roster', 'payroll-finalize' => 'Finalisasi batch', 'bank-export', 'report-export' => 'Siapkan ekspor', 'reconciliation' => 'Catat hasil', 'close' => 'Tutup periode', 'reopen' => 'Buat revisi', 'demo' => 'Jalankan skenario', default => 'Simpan contoh' } }} · belum aktif</button></div></fieldset>
    <dl class="profile-fields"><div><dt>Langkah / pihak berikutnya</dt><dd>{{ $spec['next'] }}</dd></div><div><dt>Status visual</dt><dd>{{ $state === 'invalid' ? 'Contoh validasi gagal, belum dikirim' : 'Contoh isian, belum dikirim' }}</dd></div></dl>
    @if($spec['note'])<p class="request-description">{{ $spec['note'] }}</p>@endif
    @if($screen === 'demo')<a class="inline-link" href="{{ route('prototype.state', ['state' => 'confirmation']) }}">Lihat konfirmasi reset skenario</a>@endif
</section>
