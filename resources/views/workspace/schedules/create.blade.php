<x-identity-layout title="Susun roster">
    <p>Pilih karyawan, periode dan hari kerja. Hari yang tidak dipilih dicatat sebagai hari libur pada periode ini.</p>
    <form class="identity-form" method="GET" action="{{ route('workspace.schedules.create') }}">
        <x-core-field name="q" label="Cari karyawan" :value="$q" maxlength="100" />
        <x-core-field name="as_of" label="Tanggal acuan akses karyawan" type="date" :value="$date" required />
        <button class="secondary-button" type="submit">Cari pilihan karyawan</button>
    </form>
    <form class="identity-form" method="POST" action="{{ route('workspace.schedules.store') }}">
        @csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <fieldset><legend>Karyawan yang dijadwalkan</legend><p class="history-note">Maksimal 50 pilihan ditampilkan. Gunakan pencarian untuk menemukan karyawan lain; pencarian baru menghapus pilihan yang belum disimpan.</p>
            @forelse ($people as $person)<label class="roster-checkbox"><input type="checkbox" name="employee_ids[]" value="{{ $person->id }}" @checked(in_array((string) $person->id, array_map('strval', old('employee_ids', [])), true))><span>{{ $person->name }} · {{ $person->employee_number }}</span></label>@empty<p>Tidak ada karyawan yang dapat dijadwalkan pada pilihan ini.</p>@endforelse
        </fieldset>
        <x-core-field name="starts_on" label="Tanggal mulai roster" type="date" :value="old('starts_on', $date)" required />
        <x-core-field name="ends_on" label="Tanggal terakhir roster" type="date" :value="old('ends_on', $date)" help="Rentang maksimal 90 hari antara tanggal mulai dan terakhir." required />
        <fieldset><legend>Hari kerja</legend><p class="history-note">Pilih berdasarkan jadwal yang berlaku. Tanpa pilihan, seluruh periode menjadi hari libur.</p>
            @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $value => $label)<label class="roster-checkbox"><input type="checkbox" name="working_days[]" value="{{ $value }}" @checked(in_array((string) $value, array_map('strval', old('working_days', [])), true))><span>{{ $label }}</span></label>@endforeach
        </fieldset>
        <label class="roster-checkbox"><input type="checkbox" name="confirm_all_off" value="1" @checked(old('confirm_all_off'))><span>Jika tidak ada hari kerja dipilih, saya mengonfirmasi seluruh periode sebagai hari libur.</span></label>
        <x-core-field name="shift_template_id" label="Template shift untuk hari kerja" type="select" required>
            <option value="">Pilih template</option>
            @foreach ($templates as $template)<option value="{{ $template['id'] }}" @selected((string) old('shift_template_id') === (string) $template['id'])>{{ $template['name'] }} · {{ substr($template['starts_local'], 0, 5) }}–{{ substr($template['ends_local'], 0, 5) }}{{ $template['end_day_offset'] ? ' (+1 hari)' : '' }}</option>@endforeach
        </x-core-field>
        @if ($templatePage['last_page'] > 1)<p><a class="inline-link" href="{{ route('workspace.schedules.create', ['q' => $q, 'as_of' => $date, 'page' => $templatePage['current_page'] < $templatePage['last_page'] ? $templatePage['current_page'] + 1 : 1]) }}">Lihat pilihan template berikutnya</a> · Berpindah halaman menghapus isian yang belum disimpan.</p>@endif
        @if ($templates->isEmpty())<p class="identity-notice">Belum ada template aktif pada halaman ini. Konfigurasi lokasi dan template shift diperlukan sebelum roster dibuat.</p>@endif
        <button class="primary-button" type="submit">Buat draf untuk ditinjau</button>
    </form>
    <p><a class="inline-link" href="{{ route('workspace.schedules') }}">Kembali ke roster</a></p>
</x-identity-layout>
