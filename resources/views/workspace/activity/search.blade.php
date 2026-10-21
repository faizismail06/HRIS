<x-identity-layout title="Pencarian">
    <form class="identity-form" method="GET" action="{{ route('workspace.search') }}">
        <x-core-field name="q" label="Cari karyawan, pengajuan, dokumen atau aset" type="search" :value="$q" minlength="2" maxlength="80" help="Gunakan nama, nomor karyawan, jenis pengajuan, referensi atau kode aset. Minimal dua karakter." required />
        <x-core-field name="as_of" label="Tanggal acuan penempatan karyawan" type="date" :value="$as_of" :max="today()->toDateString()" required />
        <button class="primary-button" type="submit">Cari</button>
    </form>
    @if ($q !== '')
        <h2>Hasil untuk “{{ $q }}”</h2>
        <p class="history-note">Maksimal 10 hasil per kategori, sesuai izin saat ini. Tanggal acuan hanya berlaku pada pencarian karyawan.</p>
        <ul class="identity-directory">
            @forelse ($data as $item)
                @php
                    $url = match ($item['type']) {
                        'employee' => route('workspace.employees.show', ['employee' => $item['id'], 'as_of' => $as_of]),
                        'request' => route('workspace.requests.show', $item['id']),
                        'document' => url('/app/api/documents/'.$item['id'].'/download'),
                        default => null,
                    };
                    $label = $item['type'] === 'request' ? (App\Http\Controllers\WorkflowWorkspaceController::KINDS[$item['label']] ?? str_replace('_', ' ', $item['label'])) : $item['label'];
                @endphp
                <li><p>{{ ['employee' => 'Karyawan', 'request' => 'Pengajuan', 'document' => 'Dokumen', 'asset' => 'Aset'][$item['type']] ?? $item['type'] }}</p>
                    @if ($url)<a class="inline-link" href="{{ $url }}">{{ $label }}{{ $item['type'] === 'document' ? ' · unduh PDF' : '' }}</a>@else<strong>{{ $label }}</strong>@endif
                    <p>{{ $item['reference'] }}</p>
                    @if (isset($item['status']))<p>{{ App\Http\Controllers\WorkflowWorkspaceController::STATUSES[$item['status']] ?? $item['status'] }}</p>@endif
                    @if (!$url)<p class="history-note">Layar rincian aset belum terhubung.</p>@endif
                </li>
            @empty<li><h2>Belum ditemukan hasil</h2><p>Periksa ejaan atau gunakan kata pencarian lain. Hasil hanya mencakup data yang dapat Anda akses.</p></li>@endforelse
        </ul>
    @else<p>Temukan data yang Anda butuhkan untuk melanjutkan pekerjaan.</p>@endif
</x-identity-layout>
