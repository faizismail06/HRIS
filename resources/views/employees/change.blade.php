<x-identity-layout :title="'Ajukan perubahan · '.$employee->name">
    <nav class="identity-nav" aria-label="Jenis perubahan karyawan"><a class="inline-link" href="{{ route('workspace.employees.change', ['employee' => $employee->id, 'kind' => 'identity']) }}" @if ($kind === 'identity') aria-current="page" @endif>Identitas kerja</a><a class="inline-link" href="{{ route('workspace.employees.change', ['employee' => $employee->id, 'kind' => 'assignment']) }}" @if ($kind === 'assignment') aria-current="page" @endif>Penempatan</a></nav>
    <p class="history-note">Pengajuan memerlukan persetujuan HR Admin lain. Pengaju dan karyawan yang menjadi subjek tidak dapat menyetujui pengajuannya sendiri.</p>
    <form class="identity-form" method="POST" action="{{ route('workspace.employees.propose', $employee->id) }}">
        @csrf
        <input type="hidden" name="base_version" value="{{ $employee->version }}">
        <input type="hidden" name="kind" value="{{ $kind }}">
        @if ($kind === 'identity')
            <x-core-field name="name" label="Nama lengkap" :value="old('name', $employee->name)" maxlength="160" required />
            <x-core-field name="work_email" label="Email kerja (opsional)" type="email" :value="old('work_email', $employee->work_email)" maxlength="255" />
            <p class="history-note">Perubahan ini hanya mencakup nama dan email kerja. Data bank, identitas nasional, dan payroll memerlukan verifikasi terpisah.</p>
        @else
            <x-core-field name="effective_on" label="Mulai berlaku" type="date" :min="today()->addDay()->toDateString()" :value="old('effective_on')" required help="Setelah hari ini, di dalam periode penempatan yang sudah ada. Penempatan hari ini tetap berlaku sampai tanggal ini." />
            @include('employees.placement-fields')
        @endif
        <x-core-field name="reason" label="Alasan perubahan" type="textarea" rows="4" maxlength="1000" :value="old('reason')" required help="Jelaskan kebutuhan kerja. Jangan tulis kata sandi atau data medis." />
        <button class="primary-button" type="submit">Kirim pengajuan perubahan</button>
    </form>
    <a class="inline-link" href="{{ route('workspace.employees.show', $employee->id) }}">Kembali ke profil</a>
</x-identity-layout>
