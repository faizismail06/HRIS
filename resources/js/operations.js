const make = (tag, text, className) => {
    const el = document.createElement(tag);
    if (text !== undefined) el.textContent = text;
    if (className) el.className = className;
    return el;
};

document.querySelectorAll('[data-operation-search]').forEach((input) => {
    input.addEventListener('input', () => {
        let count = 0;
        document.querySelectorAll('[data-operation-section]').forEach((section) => {
            let visible = 0;
            section.querySelectorAll('[data-operation-item]').forEach((item) => {
                item.hidden = !item.textContent.toLocaleLowerCase('id').includes(input.value.toLocaleLowerCase('id'));
                if (!item.hidden) visible++;
            });
            section.hidden = !visible;
            count += visible;
        });
        document.querySelector('[data-operation-empty]').hidden = count > 0;
    });
});

document.querySelectorAll('[data-operation-form]').forEach((form) => {
    const schema = JSON.parse(form.querySelector('[data-operation-schema]').textContent);
    const fields = form.querySelector('[data-operation-fields]');
    const values = schema.values.payload || schema.values;
    let sequence = 0;
    const inputs = new Map();
    const lookups = [];
    let refreshMapping;
    const root = (name) => form.elements.namedItem(name);
    const read = (name) => root(name)?.value || '';
    const setVersion = (name, value, overwrite) => {
        const input = root(name);
        if (input && (overwrite || !input.value)) input.value = value ?? '';
    };
    const profileVersion = async (overwrite = false) => {
        if (!root('base_version') || !read('category') || !read('employee_id')) return;
        if (!overwrite && read('base_version')) return;
        const url = new URL(schema.profileVersionUrl, window.location.origin);
        url.searchParams.set('employee_id', read('employee_id'));
        url.searchParams.set('category', read('category'));
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error();
            const data = await response.json();
            setVersion('base_version', data.version, overwrite);
        } catch {
            setVersion('base_version', '', true);
            form.querySelector('[data-operation-status]').textContent = 'Versi profil tidak dapat dibaca. Periksa izin dan pilih kembali karyawan.';
        }
    };

    function render(specs, parent, prefix = '', initial = {}, optional = false) {
        specs.forEach((field) => {
            const name = prefix ? `${prefix}[${field.name}]` : field.name;
            const value = initial?.[field.name] ?? field.fixed ?? '';
            if (['group', 'repeat', 'map'].includes(field.type)) {
                const group = make('fieldset', undefined, 'operation-group');
                group.append(make('legend', field.label));
                parent.append(group);
                if (field.type === 'group') {
                    render(field.fields, group, name, value || {}, optional || !field.required);
                    return;
                }
                const rows = make('div');
                group.append(rows);
                let index = 0;
                const add = make('button', 'Tambah baris', 'secondary-button');
                add.type = 'button';
                const addRow = (data = {}) => {
                    if (rows.children.length >= (field.max || 40)) return;
                    const row = make('fieldset', undefined, 'operation-row');
                    row.append(make('legend', `${field.label} · baris ${index + 1}`));
                    render(field.fields, row, `${name}[${index++}]`, data, optional);
                    const remove = make('button', 'Hapus baris ini', 'secondary-button');
                    remove.type = 'button';
                    remove.addEventListener('click', () => { row.remove(); add.disabled = false; });
                    row.append(remove);
                    rows.append(row);
                    add.disabled = rows.children.length >= (field.max || 40);
                };
                let entries = Array.isArray(value) ? value : [];
                if (field.type === 'map' && value && !Array.isArray(value)) entries = Object.entries(value).map(([key, val]) => ({key, value: val}));
                entries.forEach(addRow);
                if (field.name === 'mapping') {
                    refreshMapping = (meta) => {
                        const current = [...rows.children].map((row) => ({key: row.querySelector('[name$="[key]"]')?.value || '', value: row.querySelector('[name$="[value]"]')?.value || ''}));
                        field.fields = [{name: 'key', label: 'Kolom tujuan', type: 'select', required: true, options: Object.fromEntries(meta.fields.map((name) => [name, name.replaceAll('_', ' ')]))}, {name: 'value', label: 'Kolom dari berkas', type: 'select', required: true, options: Object.fromEntries(meta.headers.map((name) => [name, name]))}];
                        rows.replaceChildren();
                        index = 0;
                        current.forEach(addRow);
                    };
                }
                add.addEventListener('click', () => addRow());
                group.append(add);
                return;
            }
            const wrapper = make('div', undefined, 'operation-field');
            wrapper.hidden = field.type === 'hidden';
            parent.append(wrapper);
            const id = prefix ? `operation-field-${sequence++}` : field.name;
            const label = make('label', field.label + (field.required ? ' *' : ''));
            label.htmlFor = id;
            if (field.type !== 'hidden') wrapper.append(label);
            const select = ['select', 'boolean', 'lookup', 'multi'].includes(field.type);
            const input = make(select ? 'select' : (['textarea', 'lines'].includes(field.type) ? 'textarea' : 'input'));
            input.id = id;
            input.name = name + (field.type === 'multi' ? '[]' : '');
            input.required = field.required && !optional && field.type !== 'hidden';
            inputs.set(name, input);
            const path = name.replaceAll('[', '.').replaceAll(']', '');
            const error = schema.errors?.[path] || schema.errors?.[`payload.${path}`];
            if (error?.length) {
                input.setAttribute('aria-invalid', 'true');
                input.setAttribute('aria-describedby', `${id}-error`);
                const message = make('p', error.join(' '), 'identity-field-error');
                message.id = `${id}-error`;
                wrapper.append(message);
            }
            if (select) {
                if (field.type === 'multi') { input.multiple = true; input.size = 5; }
                else input.append(new Option('Pilih…', ''));
                let options = field.options || {};
                if (field.type === 'boolean') options = {1: 'Ya', 0: 'Tidak'};
                Object.entries(options).forEach(([key, text]) => input.append(new Option(text, key, false, String(value) === key)));
            } else if (!['textarea', 'lines'].includes(field.type)) {
                input.type = ({instant: 'datetime-local', money: 'text', version: 'number', 'employee-version': 'number', 'profile-version': 'number', checksum: 'text'})[field.type] || field.type;
                if (field.type === 'money') { input.inputMode = 'decimal'; input.pattern = '-?[0-9]{1,13}(\\.[0-9]{1,2})?'; }
                if (['version', 'employee-version', 'profile-version', 'checksum'].includes(field.type)) { input.readOnly = true; input.placeholder = 'Pilih catatan untuk memuat versi'; }
                if (field.type === 'file') input.accept = '.csv,.xlsx';
            }
            if (!select && field.type !== 'file') input.value = field.type === 'lines' && Array.isArray(value) ? value.join('\n') : value;
            if (['lookup', 'multi'].includes(field.type)) {
                const search = make('input');
                search.type = 'search';
                search.placeholder = 'Cari nama, kode, atau referensi';
                search.setAttribute('aria-label', `Cari ${field.label}`);
                const state = make('p', '', 'history-note');
                state.setAttribute('role', 'status');
                const more = make('button', 'Muat pilihan berikutnya', 'secondary-button');
                more.type = 'button';
                more.hidden = true;
                wrapper.append(search);
                let cursor = null;
                let controller;
                let metas = new Map();
                let timer;
                let selected = field.type === 'multi' ? (Array.isArray(value) ? value.map(String) : []) : [String(value)];
                const load = async (append = false, target = '') => {
                    controller?.abort();
                    controller = new AbortController();
                    const url = new URL(`${schema.optionsUrl}/${field.source}`, window.location.origin);
                    const term = target || search.value.trim();
                    if (term) url.searchParams.set('q', term);
                    if (append && cursor) url.searchParams.set('after', cursor);
                    if (read('employee_id') && field.source !== 'people') url.searchParams.set('employee_id', read('employee_id'));
                    if (field.source === 'swap-shifts') {
                        if (!read('source_shift_id')) { state.textContent = 'Pilih shift Anda terlebih dahulu.'; return; }
                        url.searchParams.set('source_shift_id', read('source_shift_id'));
                    }
                    state.textContent = 'Memuat pilihan…';
                    more.disabled = true;
                    try {
                        const response = await fetch(url, {signal: controller.signal, headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                        if (!response.ok) throw new Error();
                        const data = await response.json();
                        if (!append) {
                            // Retain selected options while searching, including multi-select references.
                            [...input.options].filter((o) => o.value && !o.selected).forEach((o) => o.remove());
                        }
                        data.data.forEach((item) => {
                            metas.set(String(item.value), item.meta || {});
                            let option = [...input.options].find((o) => o.value === String(item.value));
                            if (!option) { option = new Option(item.label, item.value); input.append(option); }
                            option.textContent = item.label;
                            if (selected.includes(String(item.value))) option.selected = true;
                        });
                        selected = [...input.selectedOptions].map((o) => o.value);
                        cursor = data.next;
                        more.hidden = !cursor;
                        more.disabled = false;
                        state.textContent = data.data.length ? 'Pilihan mengikuti izin akun Anda.' : 'Tidak ada pilihan yang sesuai. Ubah pencarian atau periksa akses.';
                        update(false);
                    } catch (error) {
                        if (error.name !== 'AbortError') { state.textContent = 'Pilihan tidak dapat dimuat. Periksa koneksi atau izin lalu cari kembali.'; more.hidden = true; }
                    }
                };
                const update = (overwrite = true) => {
                    const meta = metas.get(input.value);
                    if (!meta) return;
                    if (field.name === 'employee_id') {
                        setVersion('employee_version', meta.version, overwrite);
                        if (!read('category')) setVersion('base_version', meta.version, overwrite);
                        if (!root('record_id')) setVersion('expected_version', meta.version, overwrite);
                        profileVersion(overwrite);
                    }
                    if (field.name === 'record_id') { setVersion('expected_version', meta.version, overwrite); setVersion('checksum', meta.checksum, overwrite); }
                    if (field.name === 'id' && prefix) {
                        const version = inputs.get(`${prefix}[expected_version]`);
                        if (version && (overwrite || !version.value)) version.value = meta.version ?? '';
                    }
                    if (field.name === 'contract_id') setVersion('contract_version', meta.version, overwrite);
                    if (field.name === 'lifecycle_case_id') setVersion('case_version', meta.version, overwrite);
                    if (meta.headers && meta.fields) {
                        refreshMapping?.(meta);
                        const mapping = form.querySelector('[data-import-mapping]');
                        if (mapping) mapping.textContent = `Kolom sumber: ${meta.headers.join(', ')}. Kolom tujuan: ${meta.fields.join(', ')}.`;
                    }
                    if (meta.tasks) state.textContent = 'Checklist: ' + meta.tasks.map((task) => `${task.code}: ${task.label} (${task.role.replaceAll('_', ' ')})`).join('; ') + '. Gunakan kode ini untuk penanggung jawab.';
                };
                input.addEventListener('change', () => {
                    selected = [...input.selectedOptions].map((o) => o.value);
                    update();
                    if (['employee_id', 'source_shift_id'].includes(field.name)) lookups.filter((l) => ['employment-periods', 'contracts', 'swap-shifts', 'compensations'].includes(l.source)).forEach((l) => l.reload());
                });
                search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(), 250); });
                more.addEventListener('click', () => load(true));
                lookups.push({source: field.source, reload: () => { input.value = ''; selected = []; load(); }, init: () => load(false, value && !Array.isArray(value) ? String(value) : '')});
                wrapper.append(input, state, more);
            } else wrapper.append(input);
        });
    }
    render(schema.fields, fields, '', values);
    const mapping = make('p', '', 'history-note');
    mapping.dataset.importMapping = '';
    fields.append(mapping);
    // Init after all fields exist so version targets and dependent selectors are available.
    lookups.forEach((lookup) => lookup.init());
    const timezone = root('form_timezone');
    if (timezone && schema.values.form_timezone) timezone.value = schema.values.form_timezone;
    form.addEventListener('submit', () => {
        form.querySelector('[data-operation-submit]').disabled = true;
        form.querySelector('[data-operation-status]').textContent = 'Memproses tindakan…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => { form.querySelector('[data-operation-submit]').disabled = false; form.removeAttribute('aria-busy'); });
    document.querySelector('[data-operation-result]')?.focus();
});
