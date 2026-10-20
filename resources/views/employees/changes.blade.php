<x-identity-layout title="Perubahan karyawan">
    <form class="identity-form" method="GET" action="{{ route('workspace.changes') }}"><label for="status">Status<select id="status" name="status">@foreach (['pending' => 'Menunggu tinjauan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label><button class="secondary-button" type="submit">Terapkan filter</button></form>
    <ul class="identity-directory">
        @forelse ($changes as $change)
            <li><a class="inline-link" href="{{ route('workspace.changes.show', $change->id) }}">{{ $change->employee->name }} · {{ $change->kind === 'identity' ? 'Identitas kerja' : 'Penempatan' }}</a><p>Diajukan {{ $change->requester->name }} · {{ $change->created_at->format('d M Y H:i') }}</p></li>
        @empty
            <li><h2>Tidak ada pengajuan pada status ini</h2><p>Pengajuan baru dapat dibuat dari profil karyawan.</p></li>
        @endforelse
    </ul>
    {{ $changes->links() }}
</x-identity-layout>
