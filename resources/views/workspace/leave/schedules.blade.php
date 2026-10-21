<x-identity-layout title="Jadwal pemberian hak cuti">
    @include('workspace.leave._nav')
    <ul class="identity-directory">@forelse ($schedules as $schedule)<li><a class="inline-link" href="{{ route('workspace.leave.schedules.show', $schedule->id) }}">{{ $schedule->name }}</a><p>{{ ['draft' => 'Draf', 'approved' => 'Disahkan', 'stopped' => 'Dihentikan'][$schedule->status] }} · {{ $schedule->cadence === 'annual' ? 'Tahunan' : 'Bulanan' }} · {{ $schedule->minutes }} menit setiap pemberian</p></li>@empty<li>Belum ada jadwal pemberian hak.</li>@endforelse</ul>{{ $schedules->links() }}
    <section class="identity-section"><h2>Buat draf jadwal</h2><p>Kredit hanya diproses setelah jadwal disahkan HR Admin lain yang bukan penerima. Isi kebijakan yang sudah ditetapkan perusahaan.</p>
        <form class="identity-form" method="GET" action="{{ route('workspace.leave.schedules') }}"><x-core-field name="q" label="Cari pilihan penerima" :value="$q" maxlength="100" /><button class="secondary-button" type="submit">Cari karyawan sebelum mengisi</button></form>
        <form class="identity-form" method="POST" action="{{ route('workspace.leave.schedules.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
            <x-core-field name="name" label="Nama jadwal/kebijakan" :value="old('name')" maxlength="120" required />
            <x-core-field name="leave_type_id" label="Jenis cuti" type="select" required><option value="">Pilih jenis aktif dengan saldo</option>@foreach ($types as $type)<option value="{{ $type->id }}" @selected((string) old('leave_type_id') === (string) $type->id)>{{ $type->name }} · versi {{ $type->version }}</option>@endforeach</x-core-field>
            <fieldset><legend>Penerima eksplisit</legend><p class="history-note">Maksimal 50 pilihan ditampilkan. Pencarian baru menghapus pilihan/isian yang belum disimpan.</p>@forelse ($people as $person)<label class="roster-checkbox"><input name="employee_ids[]" type="checkbox" value="{{ $person->id }}" @checked(in_array((string) $person->id, array_map('strval', old('employee_ids', [])), true))><span>{{ $person->name }} · {{ $person->employee_number }}</span></label>@empty<p>Tidak ada karyawan pada pencarian ini.</p>@endforelse</fieldset>
            <x-core-field name="cadence" label="Frekuensi" type="select" required><option value="">Pilih frekuensi</option><option value="annual" @selected(old('cadence') === 'annual')>Tahunan</option><option value="monthly" @selected(old('cadence') === 'monthly')>Bulanan</option></x-core-field>
            <x-core-field name="first_grant_on" label="Tanggal pemberian pertama" type="date" :value="old('first_grant_on')" :min="today()->toDateString()" help="Tanggal 1–28, hari ini atau mendatang. Jadwal maksimal dua tahun." required />
            <x-core-field name="last_schedule_on" label="Tanggal terakhir jadwal berlaku (termasuk)" type="date" :value="old('last_schedule_on')" required />
            <x-core-field name="minutes" label="Kredit setiap pemberian (menit kerja)" type="number" min="1" max="1000000" :value="old('minutes')" required />
            <x-core-field name="minimum_tenure_days" label="Minimal masa kerja penerima (hari)" type="number" min="0" max="36500" :value="old('minimum_tenure_days')" required />
            <x-core-field name="expiry_rule" label="Masa berlaku kredit" type="select" required><option value="">Pilih aturan yang disahkan</option><option value="calendar_year_end" @selected(old('expiry_rule') === 'calendar_year_end')>Berakhir 31 Desember pada tahun pemberian</option></x-core-field>
            <x-core-field name="evidence" label="Dasar kebijakan dan referensi bukti" type="textarea" rows="4" maxlength="4000" :value="old('evidence')" required />
            <button class="primary-button" type="submit">Simpan draf untuk disahkan</button>
        </form>
    </section>
</x-identity-layout>
