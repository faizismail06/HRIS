<x-identity-layout title="Masuk ke ruang kerja">
    @if (\App\Domain\Identity\MfaPolicy::skippedForLocalTesting())
        <p>Mode testing lokal: masuk dengan email dan kata sandi. Verifikasi dua langkah sedang dinonaktifkan.</p>
    @else
        <p>Gunakan akun kerja yang diberikan admin. Setelah masuk, verifikasi dengan aplikasi autentikator.</p>
    @endif
    <form class="identity-form" method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Email kerja<input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus maxlength="255" @error('email') aria-invalid="true" @enderror></label>
        <label for="password">Kata sandi<input id="password" type="password" name="password" autocomplete="current-password" required></label>
        <button class="primary-button" type="submit">Masuk</button>
    </form>
    <p class="history-note"><a class="inline-link" href="{{ route('password.request') }}">Lupa kata sandi?</a> · Belum punya akun? Hubungi admin perusahaan.</p>
</x-identity-layout>
