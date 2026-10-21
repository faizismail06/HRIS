document.querySelectorAll('[data-demo-operator]').forEach((panel) => {
    const state = panel.querySelector('[data-demo-status]');
    const token = panel.querySelector('[data-demo-token]');
    const call = async (action, data) => {
        if (!token.value) throw new Error('Isi token operator instalasi demo.');
        const response = await fetch(`/demo/${action}`, {
            method: data ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': panel.dataset.csrf, 'X-Demo-Operator-Token': token.value},
            ...(data ? {body: JSON.stringify(data)} : {}),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = Object.values(result.errors || {}).flat();
            throw new Error(errors.join(' ') || (response.status === 403 ? 'Token atau izin operator tidak valid.' : response.status === 419 ? 'Sesi berubah. Muat ulang halaman lalu isi token kembali.' : 'Simulasi ditolak. Periksa persona, shift dan aturan skenario.'));
        }
        return result;
    };
    panel.querySelector('[data-demo-personas]').addEventListener('click', async (event) => {
        event.currentTarget.disabled = true;
        state.textContent = 'Memuat persona…';
        try {
            const result = await call('personas');
            const select = panel.querySelector('[name="persona"]');
            select.replaceChildren(new Option('Pilih persona', ''));
            result.personas.forEach((code) => select.append(new Option(code.replaceAll('_', ' '), code)));
            state.textContent = 'Persona siap dipilih.';
        } catch (error) { state.textContent = error.message; }
        finally { panel.querySelector('[data-demo-personas]').disabled = false; }
    });
    panel.querySelectorAll('[data-demo-action]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        state.textContent = 'Memproses simulasi…';
        try {
            const data = Object.fromEntries(new FormData(form));
            const action = form.dataset.demoAction;
            if (action === 'time') { data.time += `:00${data.offset}`; delete data.offset; }
            if (action === 'clock') data.roster_shift_id = Number(data.roster_shift_id);
            await call(action, data);
            if (action === 'persona') { window.location.assign('/app'); return; }
            state.textContent = action === 'time' ? 'Waktu skenario diperbarui.' : 'Pencatatan simulasi tersimpan. Periksa proyeksi pada halaman kehadiran.';
            if (action === 'clock') form.elements.idempotency_key.value = crypto.randomUUID();
        } catch (error) { state.textContent = error.message; }
        finally { button.disabled = false; }
    }));
});
