<x-identity-layout title="Hari kerja dan libur cabang">
    <p>Kalender ini memengaruhi perhitungan tenggat kerja yang dikonfigurasi. Menandai hari libur tidak otomatis mengganti roster karyawan.</p>
    <form class="identity-form" method="POST" action="{{ route('workspace.work-calendar.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <x-core-field name="branch_id" label="Cabang" type="select" required><option value="">Pilih cabang</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</x-core-field>
        <x-core-field name="work_date" label="Tanggal" type="date" :value="old('work_date')" required />
        <x-core-field name="label" label="Nama atau keterangan tanggal" :value="old('label')" maxlength="160" required />
        <x-core-field name="is_holiday" label="Status tanggal" type="select" required><option value="">Pilih status</option><option value="1" @selected(old('is_holiday') === '1')>Hari libur</option><option value="0" @selected(old('is_holiday') === '0')>Hari kerja (koreksi hari libur)</option></x-core-field>
        <button class="primary-button" type="submit">Simpan versi tanggal</button>
    </form>
    <h2>Versi terbaru per cabang dan tanggal</h2><ul class="identity-directory">@forelse ($dates as $date)<li><strong>{{ $date->work_date }} · {{ $date->branch_name }}</strong><p>{{ $date->label }} · {{ $date->is_holiday ? 'Hari libur' : 'Hari kerja' }} · versi {{ $date->version }}</p></li>@empty<li>Belum ada tanggal khusus.</li>@endforelse</ul>{{ $dates->links() }}
    <p><a class="inline-link" href="{{ route('workspace.team-calendar') }}">Kembali ke kalender tim</a></p>
</x-identity-layout>
