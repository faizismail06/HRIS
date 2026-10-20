<x-identity-layout :title="$employee->name">
    <p>Identitas kerja per {{ $date }}.</p>
    @can('update', $employee)
        <p><a class="primary-button" href="{{ route('workspace.employees.change', $employee->id) }}">Ajukan perubahan</a></p>
        <section class="identity-section"><h2>Riwayat penempatan</h2><ul>@foreach ($employee->assignments()->with(['branch', 'department', 'job'])->orderBy('valid_from')->get() as $assignment)<li>{{ $assignment->valid_from->format('d M Y') }} — {{ $assignment->valid_to ? $assignment->valid_to->format('d M Y').' (eksklusif)' : 'seterusnya' }} · {{ $assignment->branch->name }} · {{ $assignment->department->name }} · {{ $assignment->job->name }}</li>@endforeach</ul></section>
    @endcan
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
