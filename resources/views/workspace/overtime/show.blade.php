<x-identity-layout :title="'Lembur aktual #'.$record->id">
    <p>{{ $person->name }} · {{ $person->employee_number }} · {{ $shift->work_date }} · {{ $shift->timezone }}</p>
    <p>{{ $statuses[$record->status] ?? $record->status }} · Versi {{ $record->version }}</p>
    @php($local = fn ($value) => $value ? Carbon\CarbonImmutable::parse($value)->setTimezone($shift->timezone)->format('d M Y H:i') : 'Belum diisi')
    <dl class="identity-facts">
        <div><dt>Rentang yang diotorisasi</dt><dd>{{ $local($record->planned_start) }} — {{ $local($record->planned_end) }}</dd></div>
        <div><dt>Aktual yang diajukan</dt><dd>{{ $local($record->actual_start) }} — {{ $local($record->actual_end) }}</dd></div>
        <div><dt>Kehadiran efektif</dt><dd>{{ $local($result->effective_in) }} — {{ $local($result->effective_out) }} · {{ App\Http\Controllers\AttendanceWorkspaceController::STATUSES[$result->status] ?? $result->status }}</dd></div>
        <div><dt>Durasi hasil verifikasi</dt><dd>{{ $record->payable_minutes }} menit{{ $stale ? ' · Sumber berubah; perlu verifikasi ulang' : '' }}</dd></div>
        @if ($hasException)<div><dt>Pengecualian attendance</dt><dd>Pengecualian beralasan dicatat oleh HR. Catatan privat tidak ditampilkan di layar ini.</dd></div>@endif
    </dl>
    @if ($stale)<p class="identity-notice">Sumber kehadiran berubah setelah verifikasi. Durasi sebelumnya perlu ditinjau dan diverifikasi ulang sebelum periode ditutup.</p>@endif
    @if ($closed)<p class="identity-notice">Periode kehadiran sudah ditutup. Perubahan aktual/verifikasi memerlukan penanganan periode atau penyesuaian terpisah.</p>@endif
    @if ($own && $record->status === 'approved' && !$closed)
        <section class="identity-section"><h2>Isi aktual lembur</h2><p class="history-note">Gunakan waktu lokasi shift ({{ $shift->timezone }}). Aktual harus sudah terjadi dan berada di dalam rentang otorisasi.</p>
            <form class="identity-form" method="POST" action="{{ route('workspace.overtime.actual', $record->id) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}">
                <x-core-field name="actual_start" label="Mulai aktual (waktu lokasi shift)" type="datetime-local" :value="old('actual_start', $record->actual_start ? Carbon\CarbonImmutable::parse($record->actual_start)->setTimezone($shift->timezone)->format('Y-m-d\TH:i') : '')" required />
                <x-core-field name="actual_end" label="Selesai aktual (waktu lokasi shift)" type="datetime-local" :value="old('actual_end', $record->actual_end ? Carbon\CarbonImmutable::parse($record->actual_end)->setTimezone($shift->timezone)->format('Y-m-d\TH:i') : '')" required />
                <button class="primary-button" type="submit">Simpan aktual untuk diverifikasi</button>
            </form>
        </section>
    @endif
    @if ($canVerify && !$closed && in_array($record->status, ['approved', 'verified']) && $record->actual_start && $record->actual_end)
        <section class="identity-section"><h2>Verifikasi aktual</h2><p>Periksa otorisasi dan kehadiran efektif. Istirahat shift dikurangi dari durasi yang dapat dibayar.</p>
            <form class="identity-form" method="POST" action="{{ route('workspace.overtime.verify', $record->id) }}">@csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $record->version }}">
                @if ($canExcept)<x-core-field name="exception_reason" label="Alasan pengecualian attendance oleh HR (opsional)" type="textarea" rows="3" maxlength="1000" :value="old('exception_reason')" help="Isi hanya jika HR menetapkan pengecualian karena attendance tidak mendukung rentang aktual. Catatan disimpan secara privat." />@endif
                <button class="primary-button" type="submit">{{ $record->status === 'verified' ? 'Verifikasi ulang aktual' : 'Verifikasi aktual lembur' }}</button>
            </form>
        </section>
    @elseif ($record->status === 'approved' && !$record->actual_start)<p class="identity-notice">Menunggu karyawan mengisi waktu aktual sebelum verifikasi.</p>@endif
    <p><a class="inline-link" href="{{ route('workspace.attendance.show', $shift->id) }}">Periksa kehadiran terkait</a> · <a class="inline-link" href="{{ route('workspace.overtime') }}">Kembali ke lembur</a> · <a class="inline-link" href="{{ route('workspace.overtime.show', $record->id) }}">Muat status terbaru</a></p>
</x-identity-layout>
