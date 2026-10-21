<x-identity-layout title="Ruang kerja Anda">
    <p>Selamat datang, {{ $user->name }}. Akun Anda telah melewati verifikasi dua langkah.</p>
    @if (App\Domain\Demo\DemoBoundary::enabled())<p class="identity-notice">DEMO · Data sintetis. <a class="inline-link" href="{{ route('demo.operator') }}">Buka kontrol skenario dengan token operator</a></p>@endif
    <section class="identity-section"><h2>Pengajuan dalam peninjauan</h2><ul class="identity-directory">@forelse ($recent as $item)<li><a class="inline-link" href="{{ route('workspace.requests.show', $item->id) }}">{{ App\Http\Controllers\WorkflowWorkspaceController::KINDS[$item->kind] ?? $item->kind }} #{{ $item->id }}</a><p>{{ $item->submitted_by === $user->id ? 'Pengajuan Anda' : 'Sesuai mandat akses Anda' }} · Versi {{ $item->version }}</p></li>@empty<li>Belum ada pengajuan dalam peninjauan pada daftar yang dapat Anda akses.</li>@endforelse</ul><p><a class="inline-link" href="{{ route('workspace.requests', ['status' => 'in_review']) }}">Lihat seluruh daftar peninjauan</a></p></section>
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
    <section class="identity-section"><h2>Pekerjaan lintas modul</h2><p>Kelola dokumen, checklist, aset, profil privat, payroll, klaim, impor dan laporan sesuai akses Anda.</p><p><a class="primary-button" href="{{ route('workspace.modules') }}">Buka ruang kerja</a></p></section>
</x-identity-layout>
