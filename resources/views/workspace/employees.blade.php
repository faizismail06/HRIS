<x-identity-layout title="Direktori karyawan" subtitle="Identitas kerja dan penempatan tim, sesuai cakupan akses Anda.">
    @can('create', App\Models\Employee::class)
        <x-slot:actions><a class="secondary-button" href="{{ route('workspace.changes') }}">Pengajuan perubahan</a><a class="primary-button" href="{{ route('workspace.employees.create') }}"><x-icon name="plus" size="17"/>Tambah karyawan</a></x-slot:actions>
    @endcan
    <form class="identity-form operational-filters" method="GET" action="{{ route('workspace.employees') }}">
        <label for="q">Nama atau nomor karyawan<input id="q" name="q" value="{{ request('q') }}" maxlength="100" type="search" placeholder="Cari nama atau nomor karyawan"></label>
        <label for="as_of">Berlaku pada<input id="as_of" name="as_of" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></label>
        <button class="secondary-button" type="submit">Terapkan filter</button>
    </form>
    <section class="live-panel" aria-label="Daftar karyawan">
        <header class="live-panel-heading"><div><h2>Daftar karyawan <span class="heading-count">{{ $employees->total() }}</span></h2><p>Penempatan berlaku pada {{ \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j F Y') }}.</p></div></header>
        @if($employees->isNotEmpty())
            <div class="operational-table-scroll"><table class="operational-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Departemen</th><th scope="col">Jabatan</th><th scope="col">Cabang</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>
                @foreach($employees as $employee)
                    @php($placement = $employee->assignments->first())
                    <tr><th scope="row"><a class="person-cell" href="{{ route('workspace.employees.show', ['employee' => $employee->id, 'as_of' => $date]) }}"><span class="avatar">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span><span>{{ $employee->name }}<small>{{ $employee->employee_number }}</small></span></a></th><td>{{ $placement?->department?->name ?? 'Belum ada penempatan' }}</td><td>{{ $placement?->job?->name ?? '—' }}</td><td>{{ $placement?->branch?->name ?? '—' }}</td><td><a class="inline-link" href="{{ route('workspace.employees.show', ['employee' => $employee->id, 'as_of' => $date]) }}">Lihat<x-icon name="arrow" size="16"/></a></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="live-empty"><span class="empty-symbol"><x-icon name="people" size="26"/></span><h3>Belum ada karyawan yang dapat ditampilkan</h3><p>Coba ubah filter. Jika akses belum diberikan, hubungi admin perusahaan.</p>@can('create', App\Models\Employee::class)<a class="primary-button" href="{{ route('workspace.employees.create') }}">Tambah karyawan</a>@endcan</div>
        @endif
    </section>
    <div class="operational-pagination">{{ $employees->links() }}</div>
</x-identity-layout>
