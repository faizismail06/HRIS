<x-identity-layout title="Ruang kerja">
    <p>Pilih pekerjaan yang ingin diselesaikan. Pilihan dan catatan mengikuti mandat akses akun Anda.</p>
    <nav class="workspace-actions" aria-label="Modul utama">
        <a class="inline-link" href="{{ route('workspace.requests') }}">Persetujuan</a>
        <a class="inline-link" href="{{ route('workspace.schedules') }}">Jadwal kerja</a>
        <a class="inline-link" href="{{ route('workspace.team-calendar') }}">Kalender tim</a>
        <a class="inline-link" href="{{ route('workspace.leave-balances') }}">Saldo cuti</a>
    </nav>
    @if (auth()->user()->grants()->where('role', 'auditor')->where('scope', 'company')->exists())<p><a class="inline-link" href="{{ route('workspace.audit') }}">Jejak audit perusahaan</a></p>@endif
    <label class="operation-search">Cari pekerjaan<input type="search" data-operation-search placeholder="Contoh: kontrak, payroll, impor" autocomplete="off"></label>
    <p data-operation-empty hidden role="status">Tidak ada pekerjaan yang cocok. Coba kata lain.</p>
    @foreach ($operations as $module => $items)
        <section class="identity-section" data-operation-section>
            <h2>{{ ['people' => 'Data pribadi dan pengajuan', 'administration' => 'Dokumen dan perjalanan karyawan', 'assets' => 'Aset dan perlengkapan', 'finance' => 'Payroll dan pembayaran', 'data' => 'Impor dan laporan', 'settings' => 'Pengaturan dan akses'][$module] ?? $module }}</h2>
            <ul class="operation-directory">
                @foreach ($items as $item)<li data-operation-item><a class="inline-link" href="{{ route('workspace.work', $item['key']) }}">{{ $item['title'] }}</a></li>@endforeach
            </ul>
        </section>
    @endforeach
</x-identity-layout>
