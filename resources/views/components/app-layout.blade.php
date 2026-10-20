@props(['title', 'active' => 'dashboard', 'dashboard' => false])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Operational HRIS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="hris-app" @if($dashboard) data-dashboard @endif>
    <a class="skip-link" href="#main">Langsung ke konten</a>
    <header class="app-header">
        <div class="header-inner">
            <a class="brand" href="{{ route('prototype.dashboard') }}" aria-label="Operational HRIS, ringkasan"><span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span></span><span>hris<span class="brand-dot">.</span></span></a>
            <nav class="workspace-nav" aria-label="Navigasi ruang kerja">
                @foreach(['dashboard' => 'Ringkasan', 'employees' => 'Karyawan', 'organization' => 'Organisasi', 'attendance' => 'Kehadiran', 'requests' => 'Pengajuan', 'contracts' => 'Administrasi', 'payroll' => 'Keuangan'] as $page => $label)
                    <a href="{{ route('prototype.'.$page) }}" class="nav-item {{ $active === $page ? 'is-current' : '' }}" @if($active === $page) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="persona"><span class="avatar avatar-hr" aria-hidden="true">HR</span><span><strong>Tim HR</strong><small>Persona sintetis</small></span></div>
        </div>
    </header>
    <div class="workspace">
        <main id="main" tabindex="-1">
            <div class="preview-notice"><x-icon name="info" size="17"/><p><strong>Prototipe UI</strong><span class="notice-divider">·</span>Data sintetis. Pratinjau ini tidak mengubah catatan HR.</p><a href="#tentang">Pelajari batasnya <x-icon name="arrow" size="15"/></a></div>
            @if($errors->any())
                <div class="form-errors" role="alert"><strong>Filter belum dapat diterapkan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            {{ $slot }}
            <footer id="tentang" class="preview-footer"><details><summary><x-icon name="info" size="16"/>Tentang prototipe ini<x-icon name="chevron" size="14"/></summary><div><p>Semua nama, profil, jumlah, tugas dan jadwal adalah data sintetis dengan tanggal acuan 20 Oktober 2026. Daftar 12 profil merupakan cuplikan terpisah dari agregat contoh di dashboard; keduanya bukan satu dataset operasional.</p><p>Persona HR bukan akun login. Halaman ini hanya baca saja; administrasi karyawan, persetujuan, payroll dan isolasi demo operasional belum diimplementasikan. Tidak ada identitas pribadi, rekening atau dokumen medis nyata.</p></div></details><span>Operational HRIS · Pratinjau antarmuka</span></footer>
        </main>
    </div>
</body>
</html>
