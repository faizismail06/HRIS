@props(['title', 'subtitle' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · HRIS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hris-app {{ auth()->check() ? 'operational-app' : 'identity-app' }}">
    <a class="skip-link" href="#main">Langsung ke konten</a>
    @auth
        <header class="app-header operational-header">
            <div class="operational-header-inner">
                <a class="brand" href="{{ route('workspace.home') }}" aria-label="HRIS, beranda"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span><span>hris<span class="brand-dot">.</span></span></a>
                <nav class="operational-nav" aria-label="Navigasi utama">
                    @foreach (['home' => 'Ringkasan', 'employees' => 'Karyawan', 'organization' => 'Organisasi', 'attendance' => 'Kehadiran', 'requests' => 'Pengajuan', 'modules' => 'Ruang kerja'] as $destination => $label)
                        @continue($destination === 'employees' && ! auth()->user()->can('viewAny', App\Models\Employee::class))
                        @continue($destination === 'organization' && ! App\Domain\Identity\Access::companyPermission(auth()->user(), 'organization.manage'))
                        @php($navCurrent = request()->routeIs('workspace.'.$destination, 'workspace.'.$destination.'.*') || ($destination === 'attendance' && request()->routeIs('workspace.schedules*', 'workspace.schedule-settings*', 'workspace.overtime*', 'workspace.periods*')))
                        <a class="nav-item {{ $navCurrent ? 'is-current' : '' }}" href="{{ route('workspace.'.$destination) }}" @if($navCurrent) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="operational-account">
                    <a class="operational-tool" href="{{ route('workspace.search') }}" aria-label="Cari di ruang kerja"><x-icon name="search" size="19"/></a>
                    <a class="operational-tool" href="{{ route('workspace.notifications') }}" aria-label="Notifikasi"><x-icon name="bell" size="19"/></a>
                    <details class="account-menu">
                        <summary aria-label="Menu akun {{ auth()->user()->name }}"><span class="avatar avatar-hr">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><x-icon name="chevron" size="12"/></summary>
                        <div class="account-menu-content"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span><a href="{{ route('account.security') }}">Keamanan akun</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form></div>
                    </details>
                </div>
            </div>
        </header>
    @endauth
    <main id="main" class="identity-shell" tabindex="-1">
        @guest
            <a class="brand" href="{{ route('login') }}" aria-label="HRIS, beranda">hris<span class="brand-dot">.</span></a>
        @endguest
        @auth
            <div class="operational-context"><span>{{ App\Models\Company::find(1)?->name ?? 'Ruang kerja perusahaan' }}</span><span>{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span></div>
        @endauth
        <div class="page-heading"><div><h1>{{ $title }}</h1>@if($subtitle)<p>{{ $subtitle }}</p>@endif</div>@isset($actions)<div class="heading-actions">{{ $actions }}</div>@endisset</div>
        @if (session('status'))
            <p class="identity-notice" role="status">{{ match (session('status')) {
                'two-factor-authentication-enabled' => 'Autentikator siap dipindai. Konfirmasi kode untuk menyelesaikan pengaturan.',
                'two-factor-authentication-confirmed' => 'Autentikator berhasil dikonfirmasi.',
                'two-factor-authentication-disabled' => 'Pengaturan autentikator dihapus. Siapkan autentikator baru untuk membuka data perusahaan.',
                'recovery-codes-generated' => 'Kode pemulihan baru dibuat. Kode lama tidak berlaku lagi.',
                'passwords.reset' => 'Kata sandi berhasil diperbarui. Silakan masuk kembali.',
                default => session('status'),
            } }}</p>
        @endif
        @if ($errors->any())
            <div class="identity-errors" role="alert" tabindex="-1">
                <p>Periksa kembali isian Anda.</p>
                <ul>@foreach ($errors->messages() as $field => $messages)@foreach ($messages as $error)<li>@if (in_array($field, ['email', 'password', 'code', 'recovery_code', 'password_confirmation', 'name', 'work_email', 'starts_on', 'ends_on', 'employee_number', 'timezone', 'parent_id', 'department_id', 'is_active', 'branch_id', 'job_id', 'manager_employee_id', 'effective_on', 'reason', 'review_note', 'decision']))<a class="inline-link" href="#{{ $field }}">{{ $error }}</a>@else{{ $error }}@endif</li>@endforeach@endforeach</ul>
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
