<x-identity-layout title="Jenis cuti">
    @include('workspace.leave._nav')
    <p>Isi aturan yang sudah disahkan perusahaan. Kode yang sama membuat versi baru dan menonaktifkan versi lama; jadwal pemberian yang merujuk versi lama akan berhenti memberi kredit baru.</p>
    <form class="identity-form" method="POST" action="{{ route('workspace.leave.types.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <x-core-field name="code" label="Kode jenis cuti" :value="old('code')" maxlength="40" required /><x-core-field name="name" label="Nama jenis cuti" :value="old('name')" maxlength="120" required />
        @foreach (['is_paid' => 'Cuti berbayar', 'deducts_balance' => 'Mengurangi saldo hak cuti', 'allows_half_day' => 'Mengizinkan setengah hari', 'requires_evidence' => 'Mewajibkan bukti pendukung'] as $field => $label)<x-core-field :name="$field" :label="$label" type="select" required><option value="">Pilih aturan</option><option value="1" @selected((string) old($field) === '1')>Ya</option><option value="0" @selected((string) old($field) === '0')>Tidak</option></x-core-field>@endforeach
        <x-core-field name="notice_days" label="Minimal pemberitahuan sebelum cuti (hari)" type="number" min="0" max="365" :value="old('notice_days')" required />
        <x-core-field name="minimum_tenure_days" label="Minimal masa kerja (hari)" type="number" min="0" max="3650" :value="old('minimum_tenure_days')" required />
        <button class="primary-button" type="submit">Simpan versi jenis cuti</button>
    </form>
    <h2>Versi tersimpan</h2><ul class="identity-directory">@forelse ($types as $type)<li><strong>{{ $type->name }} · {{ $type->code }}</strong><p>Versi {{ $type->version }} · {{ $type->is_active ? 'Aktif' : 'Tidak aktif' }} · pemberitahuan {{ $type->notice_days }} hari</p></li>@empty<li>Belum ada jenis cuti.</li>@endforelse</ul>{{ $types->links() }}
</x-identity-layout>
