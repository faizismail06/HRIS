for (const form of document.querySelectorAll('[data-clock-form]')) {
    const button = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('[data-clock-feedback]');
    const label = button.textContent;
    let pending = false;
    let attempt = null;
    button.disabled = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending) return;
        pending = true;
        button.disabled = true;
        feedback.textContent = 'Menyiapkan pencatatan…';

        try {
            if (!attempt) {
                const payload = Object.fromEntries(new FormData(form));
                if (form.dataset.requiresLocation === 'true') {
                    if (!window.isSecureContext || !navigator.geolocation) {
                        feedback.textContent = 'Lokasi perangkat tidak tersedia. Gunakan HTTPS/localhost atau ajukan koreksi.';
                        return;
                    }
                    feedback.textContent = 'Mengambil lokasi perangkat…';
                    let position;
                    try {
                        position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }));
                    } catch (error) {
                        feedback.textContent = error.code === 1 ? 'Izin lokasi ditolak. Izinkan lokasi pada browser lalu coba lagi, atau ajukan koreksi.' : 'Lokasi belum diperoleh. Coba di area terbuka atau ajukan koreksi.';
                        return;
                    }
                    payload.latitude = position.coords.latitude;
                    payload.longitude = position.coords.longitude;
                    payload.accuracy_m = position.coords.accuracy;
                }
                attempt = payload;
            }
            feedback.textContent = 'Menyimpan waktu kehadiran…';
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify(attempt),
            });
            if (response.redirected || [401, 403, 419].includes(response.status)) {
                feedback.textContent = 'Sesi atau izin sudah berubah. Muat ulang halaman dan masuk kembali sebelum mencoba.';
                return;
            }
            const result = await response.json().catch(() => null);
            if (!result) throw new Error('Uncertain response');
            if (response.ok) {
                window.location.assign(form.dataset.returnUrl);
                return;
            }
            if (response.status >= 500) throw new Error('Uncertain response');
            feedback.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Pencatatan ditolak. Muat status terbaru atau ajukan koreksi.');
            attempt = null;
            // A domain rejection is itself recorded: the next attempt uses a fresh key.
            form.querySelector('[name="idempotency_key"]').value = crypto.randomUUID();
        } catch {
            feedback.textContent = 'Hasil belum dapat dipastikan. Kirim ulang untuk memeriksa percobaan yang sama; jangan membuat catatan baru.';
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = attempt ? 'Kirim ulang pencatatan' : label;
            feedback.focus();
        }
    });
}
