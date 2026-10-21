<x-identity-layout :title="'Kehadiran · '.$shift->work_date">
    <p>{{ $person->name }} · {{ $person->employee_number }}</p>
    <dl class="identity-facts">
        <div><dt>Jadwal kerja</dt><dd>{{ $shift->is_day_off ? 'Hari libur' : $shift->starts_at->setTimezone($shift->timezone)->format('d M Y H:i').' — '.$shift->ends_at->setTimezone($shift->timezone)->format('d M Y H:i') }} · {{ $shift->timezone }}</dd></div>
        <div><dt>Status</dt><dd>{{ $statuses[$result->status] ?? $result->status }}{{ $closed ? ' · Periode ditutup' : '' }}</dd></div>
        <div><dt>Waktu masuk / pulang efektif</dt><dd>{{ $result->effective_in ? Illuminate\Support\Carbon::parse($result->effective_in)->setTimezone($shift->timezone)->format('d M H:i') : 'Belum tercatat' }} / {{ $result->effective_out ? Illuminate\Support\Carbon::parse($result->effective_out)->setTimezone($shift->timezone)->format('d M H:i') : 'Belum tercatat' }}</dd></div>
        <div><dt>Ringkasan durasi</dt><dd>{{ $result->work_minutes }} menit kerja · terlambat {{ $result->late_minutes }} menit · pulang awal {{ $result->early_minutes }} menit</dd></div>
        @if (!$shift->is_day_off)<div><dt>Kebijakan pencatatan</dt><dd>{{ ['onsite' => 'Di lokasi kerja · radius dan akurasi diperiksa', 'field' => 'Lapangan · akurasi lokasi diperiksa', 'remote' => 'Jarak jauh · tanpa lokasi perangkat'][$mode] ?? $mode }}{{ $requiresPhoto ? ' · Foto bukti wajib' : '' }}</dd></div>@endif
    </dl>
    @if ($own && !$shift->is_day_off)
        <section class="identity-section"><h2>Catat kehadiran</h2>
            <p class="history-note">Waktu dicatat oleh server. Lokasi perangkat hanya diminta saat Anda memilih catat masuk atau pulang. Gunakan HTTPS atau localhost untuk akses lokasi.</p>
            @if ($requiresPhoto && ($canIn || $canOut))
                <form class="identity-form" method="POST" enctype="multipart/form-data" action="{{ route('workspace.attendance.photo', $shift->id) }}">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <x-core-field name="file" label="Foto bukti kehadiran" type="file" accept="image/jpeg,image/png" capture="user" help="JPG/PNG maksimal 5 MB. Unggah sebelum clocking; bukti berlaku selama 15 menit." required />
                    <button class="secondary-button" type="submit">Unggah foto</button>
                </form>
            @endif
            @if ($canIn || $canOut)
                @php($direction = $canIn ? 'in' : 'out')
                <form class="workspace-actions" method="POST" data-clock-form data-requires-location="{{ $mode === 'remote' ? 'false' : 'true' }}" data-return-url="{{ route('workspace.attendance.show', $shift->id) }}" action="{{ route('workspace.attendance.clock', [$shift->id, $direction]) }}">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <button class="primary-button" type="submit" @disabled($mode !== 'remote')>{{ $canIn ? 'Catat masuk' : 'Catat pulang' }}</button>
                    <p data-clock-feedback role="status" tabindex="-1" class="history-note"></p>
                </form>
                <noscript><p class="identity-notice">Aktifkan JavaScript untuk mengambil lokasi perangkat. Jika tidak dapat memakai lokasi, ajukan koreksi kehadiran kepada HR.</p></noscript>
            @else<p class="identity-notice">Tidak ada pencatatan yang tersedia saat ini. Periksa waktu shift, catatan yang sudah tersimpan atau status periode; ajukan koreksi jika diperlukan.</p>@endif
            <p><a class="inline-link" href="{{ route('workspace.requests.create', ['kind' => 'correction', 'roster_shift_id' => $shift->id]) }}">Ajukan koreksi kehadiran</a></p>
        </section>
    @endif
    <section class="identity-section"><h2>Catatan asli</h2>
        <ul class="identity-directory">@forelse ($events as $event)<li>{{ $event->direction === 'in' ? 'Masuk' : 'Pulang' }} · {{ Illuminate\Support\Carbon::parse($event->occurred_at)->setTimezone($shift->timezone)->format('d M Y H:i:s T') }}<p>{{ ['DEVICE_REPORTED' => 'Lokasi perangkat', 'FIELD_DEVICE_REPORTED' => 'Lokasi perangkat lapangan', 'REMOTE_SELF_REPORTED' => 'Pencatatan jarak jauh', 'SIMULATED' => 'SIMULASI · data sintetis'][$event->provenance] ?? $event->provenance }}</p></li>@empty<li>Belum ada catatan waktu asli. Waktu efektif dapat berasal dari koreksi yang disetujui.</li>@endforelse</ul>
    </section>
    <p><a class="inline-link" href="{{ route('workspace.attendance') }}">Kembali ke jadwal</a> · <a class="inline-link" href="{{ route('workspace.attendance.show', $shift->id) }}">Muat status terbaru</a></p>
    <p><a class="inline-link" href="{{ route('workspace.overtime', ['roster_shift_id' => $shift->id]) }}">Periksa lembur shift ini</a></p>
    @if (App\Http\Controllers\AttendanceWorkspaceController::canSchedule(request()))<p><a class="inline-link" href="{{ route('workspace.schedules.replace', $shift->id) }}">Buat pengganti jadwal ini</a></p>@endif
</x-identity-layout>
