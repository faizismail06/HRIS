<x-identity-layout title="Jejak audit">
    <p>Riwayat tindakan dan referensi sumber. Nilai profil privat, gaji, alasan medis dan berkas tidak dimuat pada halaman ini.</p>
    <form class="identity-form" method="GET">
        <x-core-field name="q" label="Cari tindakan" :value="request('q')" />
        <x-core-field name="starts_on" label="Dari tanggal" type="date" :value="request('starts_on')" />
        <x-core-field name="ends_on" label="Sampai tanggal" type="date" :value="request('ends_on')" />
        <button class="primary-button" type="submit">Cari riwayat</button>
    </form>
    <ul class="identity-directory">@forelse ($entries as $entry)<li><strong>{{ str_replace(['.', '_'], ' ', $entry->action) }}</strong><p>{{ $entry->created_at }} · Pelaku #{{ $entry->actor_id ?? 'sistem' }} · {{ str_replace('_', ' ', $entry->subject_type) }} #{{ $entry->subject_id ?? '—' }} · Audit #{{ $entry->id }}</p></li>@empty<li>Tidak ada riwayat yang cocok dengan filter.</li>@endforelse</ul>
    {{ $entries->links() }}
</x-identity-layout>
