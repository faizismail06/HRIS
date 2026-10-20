<x-identity-layout :title="$type['label']">
    <nav class="identity-nav" aria-label="Pengaturan organisasi">
        @foreach (App\Domain\CoreHR\OrganizationCatalog::TYPES as $key => $item)
            <a class="inline-link" href="{{ route('workspace.organization', $key) }}" @if ($key === $kind) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
    <p class="history-note">Master yang pernah dipakai disimpan untuk riwayat. Arsipkan setelah tidak dipakai penempatan sekarang atau mendatang.</p>
    <p><a class="primary-button" href="{{ route('workspace.organization.create', $kind) }}">Tambah {{ strtolower($type['label']) }}</a></p>
    <ul class="identity-directory">
        @forelse ($records as $record)
            <li><a class="inline-link" href="{{ route('workspace.organization.edit', [$kind, $record->id]) }}">{{ $record->name }}</a><p>{{ $record->code }} · {{ $record->is_active ? 'Aktif' : 'Diarsipkan' }}</p></li>
        @empty
            <li><h2>Belum ada {{ strtolower($type['label']) }}</h2><p>Tambahkan data pertama agar dapat dipilih saat membuat karyawan.</p></li>
        @endforelse
    </ul>
    {{ $records->links() }}
</x-identity-layout>
