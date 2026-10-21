<x-identity-layout title="Kelola roster">
    <p>Susun jadwal karyawan, periksa draf, lalu publikasikan setelah konflik dan peringatan diselesaikan.</p>
    <p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.schedules.create') }}">Susun roster</a><a class="inline-link" href="{{ route('workspace.attendance', ['view' => 'team']) }}">Lihat kehadiran tim</a></p>
    <ul class="identity-directory">@forelse ($rows as $row)<li><a class="inline-link" href="{{ route('workspace.schedules.show', $row['record']->id) }}">Roster #{{ $row['record']->id }}</a><p>{{ $row['record']->status === 'draft' ? 'Draf' : 'Dipublikasikan' }} · {{ $row['count'] }} jadwal · {{ $row['starts_on'] }} — {{ $row['ends_on'] }}</p></li>@empty<li><h2>Belum ada roster yang terlihat</h2><p>Buat draf pertama atau lanjutkan ke halaman berikutnya jika tersedia.</p></li>@endforelse</ul>
    @if ($hasMore)<p><a class="secondary-button" href="{{ route('workspace.schedules', ['cursor' => $cursor]) }}">Roster berikutnya</a></p>@endif
</x-identity-layout>
