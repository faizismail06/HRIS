<x-identity-layout title="Ruang kerja Anda">
    <p>Selamat datang, {{ $user->name }}. Akun Anda telah melewati verifikasi dua langkah.</p>
    <section class="identity-section"><h2>Akses yang diberikan</h2>
        <ul>@forelse ($user->grants as $grant)<li>{{ str_replace('_', ' ', $grant->role) }} · {{ $grant->scope }}</li>@empty<li>Belum ada peran. Hubungi admin perusahaan untuk meminta akses.</li>@endforelse</ul>
    </section>
    @can('viewAny', App\Models\Employee::class)
        <p><a class="primary-button" href="{{ route('workspace.employees') }}">Buka direktori karyawan</a></p>
    @endcan
    <p class="history-note">Modul operasional sedang dibangun. Direktori menampilkan identitas kerja sesuai izin akun; workflow transaksi belum tersedia.</p>
</x-identity-layout>
