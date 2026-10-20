<x-identity-layout title="Tambah karyawan">
    <p>Buat identitas kerja, periode kerja, dan penempatan awal sekaligus. Data akun login dibuat terpisah oleh admin.</p>
    @if ($branches->isEmpty() || $departments->isEmpty() || $jobs->isEmpty())
        <p class="identity-notice">Cabang, departemen, dan jabatan aktif harus tersedia terlebih dahulu. Hubungi HR Admin untuk melengkapi organisasi.</p>
    @else
        <form class="identity-form" method="POST" action="{{ route('workspace.employees.store') }}">
            @csrf
            <x-core-field name="employee_number" label="Nomor karyawan" :value="old('employee_number')" maxlength="40" pattern="[A-Z0-9][A-Z0-9_-]*" required help="Kode unik dengan huruf besar, angka, garis bawah atau tanda hubung. Nomor ini tidak dapat diubah setelah dibuat." />
            <x-core-field name="name" label="Nama lengkap" :value="old('name')" maxlength="160" autocomplete="name" required />
            <x-core-field name="work_email" label="Email kerja (opsional)" type="email" :value="old('work_email')" maxlength="255" autocomplete="email" />
            <x-core-field name="starts_on" label="Mulai bekerja" type="date" :value="old('starts_on', today()->toDateString())" required />
            <x-core-field name="ends_on" label="Tanggal akhir eksklusif (opsional)" type="date" :value="old('ends_on')" help="Hari pertama sudah tidak bekerja, bukan hari kerja terakhir. Kosongkan jika belum ditentukan." />
            @include('employees.placement-fields', ['placement' => null])
            <button class="primary-button" type="submit">Tambahkan karyawan</button>
        </form>
    @endif
    <a class="inline-link" href="{{ route('workspace.employees') }}">Kembali ke direktori</a>
</x-identity-layout>
