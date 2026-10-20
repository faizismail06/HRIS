<x-identity-layout title="Pulihkan kata sandi">
    <p>Masukkan email kerja untuk meminta tautan pemulihan.</p>
    <form class="identity-form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <label for="email">Email kerja<input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
        <button class="primary-button" type="submit">Kirim tautan pemulihan</button>
    </form>
    <p class="history-note"><a class="inline-link" href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
</x-identity-layout>
