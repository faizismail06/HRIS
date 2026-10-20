<x-identity-layout title="Keamanan akun">
    <p>{{ $user->email }} · Autentikasi dua langkah wajib sebelum membuka data perusahaan.</p>
    @if (! $user->two_factor_secret)
        <section class="identity-section"><h2>Aktifkan autentikator</h2>
            <p>Siapkan aplikasi autentikator di ponsel. Anda akan memindai QR lalu memasukkan kode untuk mengonfirmasi.</p>
            <form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="primary-button" type="submit">Siapkan autentikator</button></form>
        </section>
    @elseif (! $user->two_factor_confirmed_at)
        <section class="identity-section"><h2>Pindai dan konfirmasi</h2>
            <p>Pindai QR ini dengan aplikasi autentikator. Jangan bagikan QR atau kode pemulihan.</p>
            <div class="identity-qr" role="img" aria-label="QR pengaturan autentikator untuk akun Anda">{!! $user->twoFactorQrCodeSvg() !!}</div>
            <details class="identity-recovery"><summary>Masukkan kunci secara manual</summary>
                <p>Pada aplikasi autentikator, pilih tambah akun dengan kunci penyiapan. Gunakan jenis berbasis waktu (TOTP).</p>
                <p class="break-text">Penerbit: {{ config('app.name') }} · Akun: {{ $user->email }}</p>
                <p class="break-text"><code>{{ Laravel\Fortify\Fortify::currentEncrypter()->decrypt($user->two_factor_secret) }}</code></p>
            </details>
            <form class="identity-form" method="POST" action="{{ route('two-factor.confirm') }}">
                @csrf
                <label for="code">Kode autentikator<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                <button class="primary-button" type="submit">Konfirmasi autentikator</button>
            </form>
        </section>
    @else
        <section class="identity-section"><h2>Autentikator aktif</h2>
            <p>Akun Anda dilindungi autentikasi dua langkah. Simpan kode pemulihan di tempat pribadi agar tetap bisa masuk jika ponsel tidak tersedia.</p>
            <details class="identity-recovery"><summary>Tampilkan kode pemulihan</summary>
                <ul class="identity-codes">@foreach ($user->recoveryCodes() as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
            </details>
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<button class="secondary-button" type="submit">Ganti semua kode pemulihan</button><p class="history-note">Seluruh kode lama akan berhenti berlaku.</p></form>
        </section>
        <p><a class="primary-button" href="{{ route('workspace.home') }}">Buka ruang kerja</a></p>
    @endif
    @if ($user->two_factor_secret)
        <details class="identity-recovery"><summary>Ganti aplikasi autentikator</summary>
            <p>Hapus pengaturan saat ini, lalu siapkan aplikasi yang baru. Data perusahaan terkunci sampai autentikator baru dikonfirmasi.</p>
            <form method="POST" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')<button class="secondary-button" type="submit">Hapus pengaturan autentikator</button></form>
        </details>
    @endif
</x-identity-layout>
