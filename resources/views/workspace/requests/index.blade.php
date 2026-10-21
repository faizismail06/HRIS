<x-identity-layout title="Pengajuan dan persetujuan">
    <p>Ikuti status pengajuan Anda dan tinjau pekerjaan yang menjadi kewenangan Anda.</p>
    @include('workspace.leave._nav')
    @if (auth()->user()->employee_id)
        <p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.requests.create') }}">Buat pengajuan</a></p>
    @endif
    <form class="identity-form" method="GET" action="{{ route('workspace.requests') }}">
        <x-core-field name="status" label="Filter status" type="select">
            <option value="">Semua status</option>
            @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
        </x-core-field>
        <button class="secondary-button" type="submit">Terapkan filter</button>
    </form>
    <ul class="identity-directory">
        @forelse ($data as $record)
            <li><a class="inline-link" href="{{ route('workspace.requests.show', $record->id) }}">{{ $kinds[$record->kind] ?? ucwords(str_replace('_', ' ', $record->kind)) }} #{{ $record->id }}</a>
                <p>{{ $statuses[$record->status] ?? $record->status }} · {{ $record->submitted_by === auth()->id() ? 'Pengajuan Anda' : 'Pengajuan yang dapat Anda tinjau' }}</p>
                @if (isset($record->summary['starts_on']))<p>{{ $record->summary['starts_on'] }} — {{ $record->summary['ends_on'] ?? $record->summary['starts_on'] }}</p>@endif
            </li>
        @empty
            <li><h2>Belum ada pengajuan yang terlihat</h2><p>Coba status lain atau buat pengajuan baru. Hanya data sesuai akses Anda yang ditampilkan.</p></li>
        @endforelse
    </ul>
    @if ($has_more)
        <p><a class="secondary-button" href="{{ route('workspace.requests', ['status' => $status, 'cursor' => $next_cursor]) }}">Lihat halaman berikutnya</a></p>
    @endif
</x-identity-layout>
