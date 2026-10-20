<x-identity-layout title="Verifikasi akun">
    <p>Masukkan kode dari aplikasi autentikator Anda.</p>
    <form class="identity-form" method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf
        <label for="code">Kode autentikator<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label>
        <button class="primary-button" type="submit">Verifikasi dan masuk</button>
    </form>
    <details class="identity-recovery"><summary>Gunakan kode pemulihan</summary>
        <p>Setiap kode pemulihan hanya dapat digunakan sekali.</p>
        <form class="identity-form" method="POST" action="{{ route('two-factor.login.store') }}">
            @csrf
            <label for="recovery_code">Kode pemulihan<input id="recovery_code" name="recovery_code" autocomplete="off" required></label>
            <button class="secondary-button" type="submit">Masuk dengan kode pemulihan</button>
        </form>
    </details>
</x-identity-layout>
