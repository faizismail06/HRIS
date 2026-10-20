@props(['title'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · HRIS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hris-app">
    <a class="skip-link" href="#main">Langsung ke konten</a>
    <main id="main" class="identity-shell" tabindex="-1">
        <a class="brand" href="{{ auth()->check() ? route('workspace.home') : route('login') }}" aria-label="HRIS, beranda">hris<span class="brand-dot">.</span></a>
        @auth
            <nav class="identity-nav" aria-label="Navigasi akun">
                <a class="inline-link" href="{{ route('workspace.home') }}">Beranda</a>
                @can('viewAny', App\Models\Employee::class)
                    <a class="inline-link" href="{{ route('workspace.employees') }}">Karyawan</a>
                @endcan
                <a class="inline-link" href="{{ route('account.security') }}">Keamanan akun</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary-button" type="submit">Keluar</button></form>
            </nav>
        @endauth
        <div class="page-heading"><div><h1>{{ $title }}</h1></div></div>
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
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>
