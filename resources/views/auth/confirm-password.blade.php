<x-identity-layout title="Konfirmasi kata sandi">
    <p>Konfirmasi kata sandi sebelum melihat atau mengubah keamanan akun.</p>
    <form class="identity-form" method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <label for="password">Kata sandi<input id="password" type="password" name="password" autocomplete="current-password" required autofocus></label>
        <button class="primary-button" type="submit">Konfirmasi</button>
    </form>
</x-identity-layout>
