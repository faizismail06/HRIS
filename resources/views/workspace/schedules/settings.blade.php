<x-identity-layout :title="$kind === 'locations' ? 'Lokasi kerja' : 'Template shift'">
    <nav class="workspace-actions" aria-label="Konfigurasi jadwal"><a class="inline-link" href="{{ route('workspace.schedule-settings', 'locations') }}">Lokasi kerja</a><a class="inline-link" href="{{ route('workspace.schedule-settings', 'templates') }}">Template shift</a><a class="inline-link" href="{{ route('workspace.schedules') }}">Roster</a></nav>
    <p>{{ $kind === 'locations' ? 'Tetapkan cabang, zona waktu dan kebijakan pencatatan sesuai lokasi kerja perusahaan.' : 'Buat pola jam kerja untuk menyusun roster. Kode yang sama membuat versi baru; roster lama tetap memakai snapshot sebelumnya.' }}</p>
    <section class="identity-section"><h2>{{ $kind === 'locations' ? 'Tambah lokasi' : 'Simpan versi template' }}</h2>
        <form class="identity-form" method="POST" action="{{ route('workspace.schedule-settings.store', $kind) }}">
            @csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
            <x-core-field name="code" label="Kode" :value="old('code')" maxlength="40" required />
            <x-core-field name="name" label="Nama" :value="old('name')" maxlength="160" required />
            @if ($kind === 'locations')
                <x-core-field name="branch_id" label="Cabang" type="select" required><option value="">Pilih cabang aktif</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</x-core-field>
                <x-core-field name="timezone" label="Zona waktu lokasi" type="select" required><option value="">Pilih zona waktu</option>@foreach (['Asia/Jakarta' => 'WIB · Jakarta', 'Asia/Makassar' => 'WITA · Makassar', 'Asia/Jayapura' => 'WIT · Jayapura'] as $value => $label)<option value="{{ $value }}" @selected(old('timezone') === $value)>{{ $label }}</option>@endforeach</x-core-field>
                <x-core-field name="attendance_mode" label="Kebijakan lokasi saat clocking" type="select" required><option value="">Pilih kebijakan</option>@foreach (['onsite' => 'Di lokasi kerja · periksa radius dan akurasi', 'field' => 'Lapangan · periksa akurasi, tanpa batas radius', 'remote' => 'Jarak jauh · tidak mengambil lokasi perangkat'] as $value => $label)<option value="{{ $value }}" @selected(old('attendance_mode') === $value)>{{ $label }}</option>@endforeach</x-core-field>
                <p class="history-note">Koordinat di bawah adalah titik lokasi kerja yang ditetapkan HR. Pemeriksaan radius hanya berlaku untuk mode di lokasi kerja; mode jarak jauh mengabaikan lokasi perangkat.</p>
                <x-core-field name="latitude" label="Lintang lokasi kerja" type="number" step="any" min="-90" max="90" :value="old('latitude')" required />
                <x-core-field name="longitude" label="Bujur lokasi kerja" type="number" step="any" min="-180" max="180" :value="old('longitude')" required />
                <x-core-field name="radius_m" label="Radius kehadiran (meter)" type="number" min="1" max="10000" :value="old('radius_m')" required />
                <x-core-field name="max_accuracy_m" label="Batas akurasi lokasi (meter)" type="number" min="1" max="1000" :value="old('max_accuracy_m')" help="Nilai akurasi perangkat yang lebih besar dari batas ini akan ditolak." required />
                <label class="roster-checkbox"><input name="requires_photo" type="checkbox" value="1" @checked(old('requires_photo'))><span>Wajibkan foto bukti saat mencatat kehadiran</span></label>
            @else
                <x-core-field name="work_location_id" label="Lokasi dan zona waktu shift" type="select" required><option value="">Pilih lokasi aktif</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) old('work_location_id') === (string) $location->id)>{{ $location->name }} · {{ $location->timezone }}</option>@endforeach</x-core-field>
                @if ($locations->isEmpty())<p class="identity-notice">Tambahkan lokasi kerja terlebih dahulu.</p>@endif
                <x-core-field name="starts_local" label="Mulai shift (waktu lokasi)" type="time" :value="old('starts_local')" required />
                <x-core-field name="ends_local" label="Selesai shift (waktu lokasi)" type="time" :value="old('ends_local')" required />
                <x-core-field name="end_day_offset" label="Hari selesai shift" type="select" required><option value="">Pilih hari selesai</option><option value="0" @selected(old('end_day_offset') === '0')>Hari yang sama</option><option value="1" @selected(old('end_day_offset') === '1')>Hari berikutnya (shift malam)</option></x-core-field>
                <x-core-field name="tolerance_minutes" label="Toleransi keterlambatan (menit)" type="number" min="0" max="120" :value="old('tolerance_minutes')" help="Isi 0 jika perusahaan tidak memberi toleransi." required />
                <x-core-field name="clock_in_before_minutes" label="Boleh masuk paling awal sebelum shift (menit)" type="number" min="0" max="360" :value="old('clock_in_before_minutes')" required />
                <x-core-field name="clock_out_after_minutes" label="Boleh pulang paling lambat setelah shift (menit)" type="number" min="0" max="720" :value="old('clock_out_after_minutes')" required />
                <x-core-field name="half_day_split_minute" label="Batas paruh pertama setelah awal shift (menit)" type="number" min="1" max="1439" :value="old('half_day_split_minute')" help="Opsional, untuk cuti setengah hari. Kosongkan jika belum ditetapkan." />
                <fieldset><legend>Istirahat tanpa upah (opsional)</legend><p class="history-note">Isi menit sejak awal shift. Contoh: pada shift 22.00, rentang 240–300 berarti istirahat 02.00–03.00. Kosongkan pasangan yang tidak digunakan; rentang tidak boleh bertumpuk.</p>
                    @for ($i = 0; $i < 8; $i++)
                        @if ($i > 0)<details class="identity-recovery"><summary>Rentang istirahat {{ $i + 1 }}</summary>@endif
                        <label for="break-start-{{ $i }}">Mulai istirahat {{ $i + 1 }} (menit sejak awal shift)<input id="break-start-{{ $i }}" name="break_start[{{ $i }}]" type="number" min="0" max="1439" value="{{ old('break_start.'.$i) }}"></label>
                        <label for="break-end-{{ $i }}">Selesai istirahat {{ $i + 1 }} (menit sejak awal shift)<input id="break-end-{{ $i }}" name="break_end[{{ $i }}]" type="number" min="1" max="1440" value="{{ old('break_end.'.$i) }}"></label>
                        @if ($i > 0)</details>@endif
                    @endfor
                </fieldset>
            @endif
            <button class="primary-button" type="submit">{{ $kind === 'locations' ? 'Tambahkan lokasi' : 'Simpan versi template' }}</button>
        </form>
    </section>
    <section class="identity-section"><h2>{{ $kind === 'locations' ? 'Daftar lokasi' : 'Daftar versi template' }}</h2>
        <ul class="identity-directory">@forelse ($records as $record)<li><strong>{{ $record->name }}</strong><p>{{ $record->code }} · versi {{ $record->version }} · {{ $record->is_active ? 'Aktif' : 'Tidak aktif' }}</p>@if ($kind === 'locations')<p>{{ $record->timezone }} · {{ ['onsite' => 'Di lokasi kerja', 'field' => 'Lapangan', 'remote' => 'Jarak jauh'][$record->attendance_mode] ?? $record->attendance_mode }}</p>@else<p>{{ substr($record->starts_local, 0, 5) }}–{{ substr($record->ends_local, 0, 5) }}{{ $record->end_day_offset ? ' (+1 hari)' : '' }}</p>@endif</li>@empty<li>Belum ada konfigurasi tersimpan.</li>@endforelse</ul>
        {{ $records->links() }}
    </section>
</x-identity-layout>
