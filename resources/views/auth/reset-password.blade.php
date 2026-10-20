<x-identity-layout title="Buat kata sandi baru">
    <p id="password-help">Gunakan minimal 12 karakter, huruf besar, huruf kecil, dan angka.</p>
    <form class="identity-form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <label for="email">Email kerja<input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" autocomplete="username" required></label>
        <label for="password">Kata sandi baru<input id="password" type="password" name="password" autocomplete="new-password" minlength="12" aria-describedby="password-help" required></label>
        <label for="password_confirmation">Ulangi kata sandi baru<input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
        <button class="primary-button" type="submit">Simpan kata sandi baru</button>
    </form>
</x-identity-layout>
