<x-identity-layout title="Ruang kerja Anda">
    <p>Selamat datang, {{ $user->name }}. Akun Anda telah melewati verifikasi dua langkah.</p>
    <section class="identity-section"><h2>Akses yang diberikan</h2>
        <ul>@forelse ($user->grants as $grant)<li>{{ str_replace('_', ' ', $grant->role) }} · {{ $grant->scope }}</li>@empty<li>Belum ada peran. Hubungi admin perusahaan untuk meminta akses.</li>@endforelse</ul>
    </section>
    @can('viewAny', App\Models\Employee::class)
        <p><a class="primary-button" href="{{ route('workspace.employees') }}">Buka direktori karyawan</a></p>
    @endcan
    @if (App\Domain\Identity\Access::companyPermission($user, 'employees.manage'))
        <p><a class="inline-link" href="{{ route('workspace.changes') }}">Tinjau pengajuan perubahan karyawan</a></p>
    @endif
    <p class="history-note">Modul operasional sedang dibangun. Direktori dan pengajuan perubahan identitas kerja/penempatan tersedia sesuai izin. Alur HR lainnya menyusul.</p>
</x-identity-layout>
