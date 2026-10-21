<x-identity-layout :title="$spec['title']">
    <p><a class="inline-link" href="{{ route('workspace.modules') }}">Ruang kerja</a> / {{ $spec['title'] }}</p>
    @if ($result !== null)
        <section class="identity-section" aria-label="Hasil tindakan" tabindex="-1" data-operation-result>
            <h2>{{ ($resultStatus ?? 200) >= 400 ? 'Periksa hasil tindakan' : ($spec['method'] === 'GET' ? 'Rincian' : 'Hasil tersimpan') }}</h2>
            @if (($resultStatus ?? 200) >= 400)<p class="identity-notice">Tindakan belum selesai. Periksa perubahan sumber atau kesalahan di bawah, muat catatan terbaru, lalu ulangi tindakan yang sesuai.</p>@endif
            @include('workspace.operations.result', ['value' => $result, 'depth' => 0])
            @if (isset($result['current_page'], $result['last_page']))
                <nav class="workspace-actions" aria-label="Halaman hasil">
                    @if ($result['current_page'] > 1)<a class="inline-link" href="{{ request()->fullUrlWithQuery(['page' => $result['current_page'] - 1, 'run' => 1]) }}">Sebelumnya</a>@endif
                    <span>Halaman {{ $result['current_page'] }} dari {{ $result['last_page'] }}</span>
                    @if ($result['current_page'] < $result['last_page'])<a class="inline-link" href="{{ request()->fullUrlWithQuery(['page' => $result['current_page'] + 1, 'run' => 1]) }}">Berikutnya</a>@endif
                </nav>
            @endif
            @if (isset($result['id']))
                <p class="workspace-actions">
                    @foreach (App\Support\WorkspaceOperations::related($spec['key']) as $target)
                        @php($next = App\Support\WorkspaceOperations::all()[$target] ?? null)
                        @if ($next && App\Support\WorkspaceOperations::allowed(auth()->user(), $next['gate']))<a class="inline-link" href="{{ route('workspace.work', ['operation' => $target, 'record_id' => $result['id']]) }}">{{ $next['title'] }}</a>@endif
                    @endforeach
                </p>
            @endif
        </section>
    @endif
    @if ($spec['workflow'])<p class="history-note">Tindakan ini menyimpan draf. Pengajuan memerlukan pengiriman, bukti sesuai aturan, dan persetujuan peninjau yang berwenang.</p>@endif
    @if (str_contains($spec['key'], 'payroll-policy'))<p class="identity-notice">Isi dengan kebijakan perusahaan yang telah diperiksa ahli payroll. Kebijakan draf belum dapat dipakai untuk finalisasi.</p>@endif
    <form class="identity-form operation-form" data-operation-form method="{{ $spec['method'] }}" enctype="multipart/form-data" action="{{ route('workspace.work', $spec['key']) }}">
        @if ($spec['method'] === 'POST')@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">@else<input type="hidden" name="run" value="1">@endif
        <div data-operation-fields></div>
        <noscript><p class="identity-errors">Aktifkan JavaScript untuk memuat pilihan data dan formulir ini.</p></noscript>
        @if (App\Support\WorkspaceOperations::hasType($spec['fields'], 'instant'))<label>Zona waktu untuk isian tanggal dan jam<select name="form_timezone"><option value="Asia/Jakarta">WIB · Asia/Jakarta</option><option value="Asia/Makassar">WITA · Asia/Makassar</option><option value="Asia/Jayapura">WIT · Asia/Jayapura</option><option value="UTC">UTC</option></select></label>@endif
        @if (App\Support\WorkspaceOperations::hasType($spec['fields'], 'money'))<p class="history-note">Nominal diisi dalam rupiah tanpa pemisah ribuan. Gunakan titik untuk pecahan, misalnya 12500.50.</p>@endif
        <button class="primary-button" type="submit" data-operation-submit>{{ $spec['method'] === 'GET' ? 'Tampilkan / unduh' : ($spec['workflow'] ? 'Simpan draf pengajuan' : 'Simpan tindakan') }}</button>
        <p role="status" data-operation-status></p>
        <script type="application/json" data-operation-schema>{!! Illuminate\Support\Js::encode(['fields' => $spec['fields'], 'values' => $values, 'errors' => $errors->messages(), 'optionsUrl' => url('/app/options'), 'profileVersionUrl' => route('workspace.profile-version')]) !!}</script>
    </form>
</x-identity-layout>
