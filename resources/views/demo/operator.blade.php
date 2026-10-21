<x-identity-layout title="Operator skenario demo">
    <p class="identity-notice">DEMO · Hanya data sintetis pada instalasi skenario terisolasi.</p>
    <p>Pilih persona atau ubah waktu skenario untuk pengujian. Token diisi ulang pada setiap kunjungan dan tidak disimpan di browser.</p>
    <div data-demo-operator data-csrf="{{ csrf_token() }}">
        <div class="identity-form"><label for="operator-token">Token operator<input id="operator-token" type="password" autocomplete="off" data-demo-token></label><button class="secondary-button" type="button" data-demo-personas>Muat persona yang tersedia</button></div>
        <p role="status" aria-live="polite" data-demo-status></p>
        <section class="identity-section"><h2>Masuk sebagai persona</h2>
            <form class="identity-form" data-demo-action="persona"><label for="demo-persona">Persona<select id="demo-persona" name="persona" required><option value="">Muat persona terlebih dahulu</option></select></label><button class="primary-button" type="submit">Masuk ke ruang kerja persona</button></form>
        </section>
        <section class="identity-section"><h2>Waktu skenario</h2><p class="history-note">Jam skenario dipakai layanan simulasi yang mendukungnya. Jam sistem host dan data produksi tidak berubah.</p>
            <form class="identity-form" data-demo-action="time"><label for="demo-time">Tanggal dan jam skenario<input id="demo-time" name="time" type="datetime-local" required></label><label for="demo-zone">Zona waktu<select id="demo-zone" name="offset"><option value="+07:00">WIB</option><option value="+08:00">WITA</option><option value="+09:00">WIT</option></select></label><button class="primary-button" type="submit">Atur waktu skenario</button></form>
        </section>
        @auth
            <section class="identity-section"><h2>Simulasi pencatatan kehadiran saya</h2>
                <form class="identity-form" data-demo-action="clock">
                    <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
                    <label for="demo-shift">Shift persona aktif<select id="demo-shift" name="roster_shift_id" required><option value="">Pilih shift</option>@foreach ($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->work_date }} · {{ $shift->starts_at->setTimezone($shift->timezone)->format('H:i') }}–{{ $shift->ends_at->setTimezone($shift->timezone)->format('H:i') }} · {{ $shift->timezone }}</option>@endforeach</select></label>
                    <label for="demo-direction">Pencatatan<select id="demo-direction" name="direction"><option value="in">Masuk</option><option value="out">Pulang</option></select></label>
                    <label for="demo-mode">Kondisi lokasi<select id="demo-mode" name="mode"><option value="inside">Di dalam radius</option><option value="outside">Di luar radius</option><option value="poor_accuracy">Akurasi buruk</option></select></label>
                    <button class="primary-button" type="submit">Catat simulasi</button>
                </form>
            </section>
        @endauth
    </div>
</x-identity-layout>
