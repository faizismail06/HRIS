<x-identity-layout title="Catat kredit hak cuti">
    @include('workspace.leave._nav')
    <p>Tindakan ini langsung menambahkan kredit ke ledger. Gunakan hak yang telah disahkan; kredit untuk diri sendiri harus dicatat HR Admin lain.</p>
    <form class="identity-form" method="GET" action="{{ route('workspace.leave.grant') }}"><x-core-field name="q" label="Cari pilihan karyawan" :value="$q" maxlength="100" /><button class="secondary-button" type="submit">Cari karyawan</button></form>
    <form class="identity-form" method="POST" action="{{ route('workspace.leave.grant.store') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">
        <x-core-field name="employee_id" label="Karyawan" type="select" help="Maksimal 50 pilihan; gunakan pencarian sebelum mengisi formulir." required><option value="">Pilih karyawan</option>@foreach ($people as $person)<option value="{{ $person->id }}" @selected((string) old('employee_id') === (string) $person->id)>{{ $person->name }} · {{ $person->employee_number }}</option>@endforeach</x-core-field>
        <x-core-field name="type_code" label="Jenis hak" type="select" required><option value="">Pilih jenis aktif</option>@foreach ($types as $type)<option value="{{ $type->code }}" @selected(old('type_code') === $type->code)>{{ $type->name }} · {{ $type->code }}</option>@endforeach</x-core-field>
        <x-core-field name="entitlement_year" label="Tahun hak" type="number" min="2000" max="2200" :value="old('entitlement_year')" required />
        <x-core-field name="valid_from" label="Mulai berlaku" type="date" :value="old('valid_from')" required /><x-core-field name="last_valid_on" label="Tanggal terakhir berlaku (termasuk)" type="date" :value="old('last_valid_on')" required />
        <x-core-field name="minutes" label="Kredit yang diberikan (menit kerja)" type="number" min="1" max="1000000" :value="old('minutes')" required />
        <x-core-field name="source_type" label="Sumber kredit" type="select" required><option value="">Pilih sumber</option>@foreach (['grant' => 'Pemberian hak', 'accrual' => 'Akumulasi berkala yang telah disahkan', 'adjustment' => 'Koreksi penambahan'] as $value => $label)<option value="{{ $value }}" @selected(old('source_type') === $value)>{{ $label }}</option>@endforeach</x-core-field>
        <x-core-field name="source_key" label="Referensi unik sumber hak" :value="old('source_key')" maxlength="100" help="Gunakan referensi kebijakan/transaksi unik agar sumber yang sama tidak dicatat dua kali. Carry-over dilakukan dari rincian kredit lama." required />
        <x-core-field name="reason" label="Dasar pemberian atau koreksi" type="textarea" rows="3" maxlength="1000" :value="old('reason')" required />
        <button class="primary-button" type="submit">Catat kredit ke ledger</button>
    </form>
</x-identity-layout>
