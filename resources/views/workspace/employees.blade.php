<x-identity-layout title="Direktori karyawan">
    <p>Identitas kerja sesuai cakupan akses Anda pada tanggal yang dipilih.</p>
    @can('create', App\Models\Employee::class)
        <p><a class="primary-button" href="{{ route('workspace.employees.create') }}">Tambah karyawan</a> · <a class="inline-link" href="{{ route('workspace.changes') }}">Pengajuan perubahan</a></p>
    @endcan
    <form class="identity-form" method="GET" action="{{ route('workspace.employees') }}">
        <label for="q">Nama atau nomor karyawan<input id="q" name="q" value="{{ request('q') }}" maxlength="100" type="search"></label>
        <label for="as_of">Berlaku pada<input id="as_of" name="as_of" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></label>
        <button class="secondary-button" type="submit">Terapkan filter</button>
    </form>
    <ul class="identity-directory">
        @forelse ($employees as $employee)
            <li><a class="inline-link" href="{{ route('workspace.employees.show', ['employee' => $employee->id, 'as_of' => $date]) }}">{{ $employee->name }}</a><p>{{ $employee->employee_number }} · {{ $employee->assignments->first()?->department?->name ?? 'Belum ada penempatan' }}</p></li>
        @empty
            <li><h2>Belum ada karyawan yang dapat ditampilkan</h2><p>Coba ubah filter. Jika akses belum diberikan, hubungi admin perusahaan.</p></li>
        @endforelse
    </ul>
    {{ $employees->links() }}
</x-identity-layout>
