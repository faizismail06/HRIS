<x-identity-layout :title="$type['label']" subtitle="Kelola struktur perusahaan untuk penempatan dan riwayat kerja karyawan.">
    <x-slot:actions><a class="primary-button" href="{{ route('workspace.organization.create', $kind) }}"><x-icon name="plus" size="17"/>Tambah {{ strtolower($type['label']) }}</a></x-slot:actions>
    <nav class="organization-tabs" aria-label="Pengaturan organisasi">
        @foreach (App\Domain\CoreHR\OrganizationCatalog::TYPES as $key => $item)
            <a href="{{ route('workspace.organization', $key) }}" @if ($key === $kind) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
    <section class="live-panel" aria-label="Daftar {{ strtolower($type['label']) }}">
        <header class="live-panel-heading"><div><h2>Daftar {{ strtolower($type['label']) }} <span class="heading-count">{{ $records->total() }}</span></h2><p>Data aktif dapat dipilih saat menambahkan penempatan.</p></div></header>
        @if($records->isNotEmpty())
            <div class="operational-table-scroll"><table class="operational-table"><thead><tr><th scope="col">Nama</th><th scope="col">Kode</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
                @foreach($records as $record)
                    <tr><th scope="row"><a class="person-cell" href="{{ route('workspace.organization.edit', [$kind, $record->id]) }}">{{ $record->name }}</a></th><td>{{ $record->code }}</td><td><span class="record-status {{ $record->is_active ? 'is-active' : '' }}">{{ $record->is_active ? 'Aktif' : 'Diarsipkan' }}</span></td><td><a class="inline-link" href="{{ route('workspace.organization.edit', [$kind, $record->id]) }}">Ubah<x-icon name="arrow" size="16"/></a></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="live-empty"><span class="empty-symbol"><x-icon name="building" size="26"/></span><h3>Belum ada {{ strtolower($type['label']) }}</h3><p>Tambahkan data pertama agar dapat dipilih saat membuat karyawan.</p><a class="secondary-button" href="{{ route('workspace.organization.create', $kind) }}">Tambah {{ strtolower($type['label']) }}</a></div>
        @endif
        <footer class="live-panel-footer"><x-icon name="info" size="16"/><span>Master yang pernah dipakai disimpan untuk riwayat. Arsipkan setelah tidak dipakai penempatan sekarang atau mendatang.</span></footer>
    </section>
    <div class="operational-pagination">{{ $records->links() }}</div>
</x-identity-layout>
