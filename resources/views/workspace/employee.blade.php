<x-identity-layout :title="$employee->name">
    <p>Identitas kerja per {{ $date }}.</p>
    <dl class="identity-facts">
        <div><dt>Nomor karyawan</dt><dd>{{ $employee->employee_number }}</dd></div>
        <div><dt>Email kerja</dt><dd>{{ $employee->work_email ?? 'Belum diisi' }}</dd></div>
        <div><dt>Status hubungan kerja</dt><dd>{{ match ($employee->statusAt($date)) { 'ACTIVE' => 'Aktif', 'OFFBOARDING' => 'Offboarding', 'PREBOARDING' => 'Pra-bergabung', default => 'Berakhir' } }}</dd></div>
        <div><dt>Cabang</dt><dd>{{ $employee->assignments->first()?->branch?->name ?? 'Belum ada penempatan' }}</dd></div>
        <div><dt>Departemen</dt><dd>{{ $employee->assignments->first()?->department?->name ?? 'Belum ada penempatan' }}</dd></div>
        <div><dt>Jabatan</dt><dd>{{ $employee->assignments->first()?->job?->name ?? 'Belum ada penempatan' }}</dd></div>
    </dl>
    <p><a class="inline-link" href="{{ route('workspace.employees', ['as_of' => $date]) }}">Kembali ke direktori</a></p>
</x-identity-layout>
