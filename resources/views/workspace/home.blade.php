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
    <section class="identity-section"><h2>Pengajuan dan persetujuan</h2>
        <p>Ajukan cuti, pembatalan cuti, lembur atau koreksi kehadiran. Pantau status dan tinjau pengajuan sesuai kewenangan Anda.</p>
        <p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.requests') }}">Buka pengajuan</a></p>
    </section>
    <section class="identity-section"><h2>Kehadiran dan jadwal</h2><p>Periksa shift, catat masuk/pulang dan susun roster sesuai kewenangan Anda.</p><p class="workspace-actions"><a class="primary-button" href="{{ route('workspace.attendance') }}">Buka kehadiran</a></p></section>
    <p class="history-note">Direktori, organisasi, perubahan karyawan, pengajuan dan kehadiran terhubung ke data aplikasi. Layar prototipe modul lain tetap memakai data sintetis.</p>
</x-identity-layout>
