<x-identity-layout :title="'Selamat datang, '.$user->name" subtitle="Lihat kabar tim dan lanjutkan pekerjaan Anda hari ini.">
    <x-slot:actions>
        @can('create', App\Models\Employee::class)
            <a class="primary-button" href="{{ route('workspace.employees.create') }}"><x-icon name="plus" size="17"/>Tambah karyawan</a>
        @else
            <a class="primary-button" href="{{ route('workspace.modules') }}">Buka ruang kerja<x-icon name="arrow" size="17"/></a>
        @endcan
    </x-slot:actions>
    @if (App\Domain\Demo\DemoBoundary::enabled())
        <p class="identity-notice">DEMO · Data sintetis. <a class="inline-link" href="{{ route('demo.operator') }}">Buka kontrol skenario dengan token operator</a></p>
    @endif
    @if (App\Domain\Identity\MfaPolicy::skippedForLocalTesting())
        <p class="local-testing-note"><x-icon name="info" size="16"/>Mode testing lokal · Verifikasi dua langkah dinonaktifkan.</p>
    @endif

    <div class="live-summary" aria-label="Ringkasan sesuai akses">
        @if($employeeCount !== null)
            <a href="{{ route('workspace.employees') }}"><span class="summary-icon"><x-icon name="people"/></span><span><strong>{{ number_format($employeeCount, 0, ',', '.') }}</strong><span>Karyawan dalam akses Anda</span></span><x-icon name="arrow" size="17"/></a>
        @endif
        @if($pendingChanges !== null)
            <a href="{{ route('workspace.changes') }}"><span class="summary-icon"><x-icon name="tasks"/></span><span><strong>{{ $pendingChanges }}</strong><span>Perubahan karyawan menunggu</span></span><x-icon name="arrow" size="17"/></a>
        @endif
        <a href="{{ route('workspace.requests', ['status' => 'in_review']) }}"><span class="summary-icon"><x-icon name="clock"/></span><span><strong>{{ count($recent) }}{{ $moreReviews ? '+' : '' }}</strong><span>Pengajuan ditampilkan untuk ditinjau</span></span><x-icon name="arrow" size="17"/></a>
    </div>
    <div class="live-dashboard-grid">
        <div class="live-dashboard-main">
            <section class="live-panel" aria-labelledby="review-title">
                <header class="live-panel-heading"><div><h2 id="review-title">Perlu ditinjau</h2><p>Pengajuan yang terlihat sesuai mandat akses Anda.</p></div><a class="inline-link" href="{{ route('workspace.requests', ['status' => 'in_review']) }}">Lihat pengajuan<x-icon name="arrow" size="16"/></a></header>
                @if(count($recent))
                    <ul class="live-review-list">
                        @foreach($recent as $item)
                            <li><span class="summary-icon"><x-icon name="tasks" size="18"/></span><div><a href="{{ route('workspace.requests.show', $item->id) }}">{{ App\Http\Controllers\WorkflowWorkspaceController::KINDS[$item->kind] ?? $item->kind }} #{{ $item->id }}</a><p>{{ $item->submitted_by === $user->id ? 'Pengajuan Anda' : 'Sesuai mandat akses Anda' }} · Versi {{ $item->version }}</p></div><a class="review-link" href="{{ route('workspace.requests.show', $item->id) }}">Tinjau<x-icon name="arrow" size="16"/></a></li>
                        @endforeach
                    </ul>
                @else
                    <div class="live-empty"><span class="empty-symbol"><x-icon name="tasks" size="26"/></span><h3>Belum ada pengajuan dalam peninjauan</h3><p>Pengajuan akan muncul di sini setelah dikirim dan dapat diakses oleh akun Anda.</p><a class="secondary-button" href="{{ route('workspace.requests') }}">Buka daftar pengajuan</a></div>
                @endif
                <footer class="live-panel-footer"><x-icon name="info" size="15"/><span>Persetujuan mengikuti kewenangan; pengaju tidak dapat menyetujui pengajuannya sendiri.</span></footer>
            </section>
            @if($employeeCount !== null)
                <section class="live-panel" aria-labelledby="people-title">
                    <header class="live-panel-heading"><div><h2 id="people-title">Karyawan terbaru</h2><p>Maksimal lima catatan terakhir dalam cakupan akses Anda.</p></div><a class="inline-link" href="{{ route('workspace.employees') }}">Direktori<x-icon name="arrow" size="16"/></a></header>
                    @if($team->isNotEmpty())
                        <div class="operational-table-scroll"><table class="operational-table"><thead><tr><th scope="col">Karyawan</th><th scope="col">Departemen</th><th scope="col">Jabatan</th><th scope="col"><span class="sr-only">Tindakan</span></th></tr></thead><tbody>@foreach($team as $employee)<tr><th scope="row"><a class="person-cell" href="{{ route('workspace.employees.show', $employee->id) }}"><span class="avatar">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span><span>{{ $employee->name }}<small>{{ $employee->employee_number }}</small></span></a></th><td>{{ $employee->assignments->first()?->department?->name ?? 'Belum ada penempatan' }}</td><td>{{ $employee->assignments->first()?->job?->name ?? '—' }}</td><td><a class="inline-link" href="{{ route('workspace.employees.show', $employee->id) }}" aria-label="Lihat {{ $employee->name }}"><x-icon name="arrow" size="17"/></a></td></tr>@endforeach</tbody></table></div>
                    @else
                        <div class="people-empty"><span class="summary-icon"><x-icon name="people" size="22"/></span><div><h3>Direktori Anda masih kosong</h3><p>Belum ada data karyawan yang dapat ditampilkan. @can('create', App\Models\Employee::class)Siapkan organisasi, lalu tambahkan karyawan pertama.@else Data akan tampil setelah tersedia dalam cakupan akses Anda.@endcan</p></div></div>
                    @endif
                </section>
            @endif
        </div>
        <div class="live-dashboard-aside">
            @if($organization !== null)
                <section class="live-setup" aria-labelledby="setup-title"><header><x-icon name="building" size="22"/><h2 id="setup-title">Siapkan ruang tim</h2></header><p>Lengkapi struktur perusahaan sebelum menambahkan penempatan karyawan.</p>
                    <ol class="setup-list">
                        @foreach(['branches' => ['Cabang', 'Lokasi dan zona waktu'], 'departments' => ['Departemen', 'Susunan tim perusahaan'], 'jobs' => ['Jabatan', 'Peran di setiap departemen']] as $kind => [$label, $description])
                            <li><span class="setup-state {{ $organization[$kind] ? 'is-done' : '' }}">@if($organization[$kind])<x-icon name="check" size="15"/>@else<span aria-hidden="true"></span>@endif<span class="sr-only">{{ $organization[$kind] ? 'Sudah tersedia' : 'Belum tersedia' }}</span></span><a href="{{ route('workspace.organization', $kind) }}"><strong>{{ $label }}<span>{{ $organization[$kind] ? $organization[$kind].' aktif' : 'Belum diisi' }}</span></strong><small>{{ $description }}</small></a><x-icon name="chevron" size="14"/></li>
                        @endforeach
                    </ol>
                    <a class="setup-action" href="{{ route('workspace.organization') }}">Kelola organisasi<x-icon name="arrow" size="17"/></a>
                </section>
            @endif
            <section class="live-panel live-shortcuts" aria-labelledby="quick-title"><h2 id="quick-title">Pekerjaan sehari-hari</h2>
                @foreach(['attendance' => ['clock', 'Kehadiran', 'Catatan masuk dan pulang'], 'schedules' => ['calendar', 'Jadwal kerja', 'Shift dan roster tim'], 'requests' => ['tasks', 'Pengajuan', 'Cuti, lembur dan koreksi'], 'modules' => ['grid', 'Semua pekerjaan', 'Dokumen, keuangan dan lainnya']] as $destination => [$icon, $label, $description])
                    <a class="daily-link" href="{{ route('workspace.'.$destination) }}"><x-icon :name="$icon" size="19"/><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><x-icon name="chevron" size="14"/></a>
                @endforeach
            </section>
            <details class="live-access"><summary>Akses yang diberikan<x-icon name="chevron" size="13"/></summary><ul>@forelse($user->grants as $grant)<li>{{ str_replace('_', ' ', $grant->role) }} · {{ $grant->scope }}</li>@empty<li>Belum ada peran. Hubungi admin perusahaan.</li>@endforelse</ul></details>
        </div>
    </div>
</x-identity-layout>
