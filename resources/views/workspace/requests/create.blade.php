<x-identity-layout :title="'Ajukan '.$kinds[$kind]">
    <nav class="workspace-actions" aria-label="Jenis pengajuan">
        @foreach ($kinds as $value => $label)<a class="inline-link" href="{{ route('workspace.requests.create', ['kind' => $value]) }}" @if($value === $kind) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>
    <p>Pengajuan disimpan sebagai draf terlebih dahulu. Anda dapat memeriksa rincian dan mengunggah bukti sebelum mengirimnya.</p>
    <form class="identity-form" method="POST" action="{{ route('workspace.requests.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <input type="hidden" name="kind" value="{{ $kind }}">
        @if ($kind === 'leave')
            <x-core-field name="leave_type_id" label="Jenis cuti" type="select" required>
                <option value="">Pilih jenis cuti</option>
                @foreach ($types as $type)<option value="{{ $type->id }}" @selected((string) old('leave_type_id') === (string) $type->id)>{{ $type->name }}{{ $type->requires_evidence ? ' · bukti wajib' : '' }} · pemberitahuan {{ $type->notice_days }} hari</option>@endforeach
            </x-core-field>
            @if ($types->isEmpty())<p class="identity-notice">Jenis cuti belum dikonfigurasi. Hubungi HR sebelum mengajukan cuti.</p>@endif
            <x-core-field name="starts_on" label="Tanggal mulai" type="date" :value="old('starts_on')" required />
            <x-core-field name="ends_on" label="Tanggal terakhir cuti" type="date" :value="old('ends_on')" required />
            <x-core-field name="segment" label="Durasi per hari" type="select" help="Setengah hari hanya untuk satu tanggal dan jenis cuti serta shift yang mendukungnya." required>
                @foreach (['full' => 'Sehari penuh', 'first_half' => 'Paruh pertama shift', 'second_half' => 'Paruh kedua shift'] as $value => $label)<option value="{{ $value }}" @selected(old('segment', 'full') === $value)>{{ $label }}</option>@endforeach
            </x-core-field>
        @elseif ($kind === 'leave_cancel')
            <x-core-field name="original_request_id" label="Cuti yang dibatalkan" type="select" help="Pembatalan ditinjau terpisah. Hak cuti dikembalikan setelah disetujui." required>
                <option value="">Pilih pengajuan cuti</option>
                @foreach ($originals as $original)<option value="{{ $original->id }}" @selected((string) old('original_request_id') === (string) $original->id)>#{{ $original->id }} · {{ $original->summary['starts_on'] ?? '—' }} — {{ $original->summary['ends_on'] ?? '—' }}</option>@endforeach
            </x-core-field>
            @if ($originals->isEmpty())<p class="identity-notice">Belum ada cuti disetujui yang dapat dipilih.</p>@endif
        @else
            <x-core-field name="roster_shift_id" label="Shift terkait" type="select" help="Waktu isian mengikuti zona shift yang dipilih. Shift yang ditampilkan: delapan hari terakhir hingga 90 hari ke depan." required>
                <option value="">Pilih shift</option>
                @foreach ($shifts as $shift)<option value="{{ $shift->id }}" @selected((string) old('roster_shift_id', request('roster_shift_id')) === (string) $shift->id)>{{ $shift->work_date }} · {{ $shift->is_day_off ? 'Hari libur' : $shift->starts_at?->setTimezone($shift->timezone)->format('H:i').'–'.$shift->ends_at?->setTimezone($shift->timezone)->format('H:i') }} · {{ $shift->timezone }}</option>@endforeach
            </x-core-field>
            @if ($shifts->isEmpty())<p class="identity-notice">Belum ada shift terpublikasi pada rentang ini. Hubungi HR untuk memeriksa jadwal.</p>@endif
            @if ($kind === 'overtime')
                <x-core-field name="planned_start" label="Mulai lembur (waktu shift)" type="datetime-local" :value="old('planned_start')" required />
                <x-core-field name="planned_end" label="Selesai lembur (waktu shift)" type="datetime-local" :value="old('planned_end')" required />
                <x-core-field name="task" label="Pekerjaan yang dilakukan" type="textarea" rows="3" maxlength="500" :value="old('task')" required />
            @else
                <x-core-field name="effective_in" label="Jam masuk yang benar (waktu shift)" type="datetime-local" :value="old('effective_in')" required />
                <x-core-field name="effective_out" label="Jam keluar yang benar (waktu shift)" type="datetime-local" :value="old('effective_out')" required />
            @endif
        @endif
        <x-core-field name="reason" label="Alasan pengajuan" type="textarea" rows="4" maxlength="1000" :value="old('reason')" required />
        <button class="primary-button" type="submit">Simpan draf</button>
    </form>
    <a class="inline-link" href="{{ route('workspace.requests') }}">Kembali ke pengajuan</a>
</x-identity-layout>
