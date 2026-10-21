import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/attendance.js', import.meta.url), 'utf8');

function screen({ locate, fetch, remote = false }) {
    let submit;
    const button = { textContent: 'Catat masuk', disabled: true };
    const feedback = { textContent: '', focus() {} };
    const key = { value: 'original-key' };
    const token = { value: 'synthetic-csrf' };
    const form = {
        action: 'https://hris.test/app/attendance/1/clock-in',
        dataset: { requiresLocation: remote ? 'false' : 'true', returnUrl: '/app/attendance/1' },
        querySelector(selector) {
            return selector.startsWith('button') ? button : selector.includes('feedback') ? feedback : selector.includes('_token') ? token : key;
        },
        addEventListener(_, listener) { submit = listener; },
    };
    const navigations = [];
    const context = {
        document: { querySelectorAll: () => [form] },
        window: { isSecureContext: true, location: { assign: (url) => navigations.push(url) } },
        navigator: { geolocation: { getCurrentPosition: locate } },
        FormData: class extends Map { constructor() { super([['_token', token.value], ['idempotency_key', key.value]]); } },
        crypto: { randomUUID: () => 'fresh-key' }, fetch,
    };
    vm.runInNewContext(source, context);
    return { button, feedback, navigations, submit: () => submit({ preventDefault() {} }) };
}

test('network uncertainty retries the identical key and coordinates without asking GPS again', async () => {
    const calls = [];
    let gps = 0;
    const ui = screen({
        locate(resolve) { gps++; resolve({ coords: { latitude: -6.2, longitude: 106.8, accuracy: 10 } }); },
        async fetch(url, options) {
            calls.push({ url, body: options.body });
            if (calls.length === 1) throw new Error('Connection interrupted after possible server acceptance');
            return { ok: true, status: 200, json: async () => ({ id: 1 }) };
        },
    });
    await ui.submit();
    assert.match(ui.feedback.textContent, /Hasil belum dapat dipastikan/);
    assert.equal(ui.button.disabled, false);
    await ui.submit();
    assert.equal(gps, 1);
    assert.deepEqual(calls[0], calls[1]);
    assert.deepEqual(ui.navigations, ['/app/attendance/1']);
});

test('location denial sends no attendance request and restores the control', async () => {
    let sent = 0;
    const ui = screen({ locate(_, reject) { reject({ code: 1 }); }, async fetch() { sent++; } });
    await ui.submit();
    assert.equal(sent, 0);
    assert.match(ui.feedback.textContent, /Izin lokasi ditolak/);
    assert.equal(ui.button.disabled, false);
});

test('a domain rejection gets a fresh key and location while remote clocking never requests GPS', async () => {
    const payloads = [];
    let gps = 0;
    const ui = screen({
        locate(resolve) { gps++; resolve({ coords: { latitude: -6.2, longitude: 106.8, accuracy: 10 } }); },
        async fetch(_, options) {
            payloads.push(JSON.parse(options.body));
            return { ok: false, status: 422, json: async () => ({ message: 'Akurasi belum memadai.' }) };
        },
    });
    await ui.submit();
    assert.match(ui.feedback.textContent, /Akurasi belum memadai/);
    await ui.submit();
    assert.equal(gps, 2);
    assert.equal(payloads[0].idempotency_key, 'original-key');
    assert.equal(payloads[1].idempotency_key, 'fresh-key');
    const remote = screen({
        remote: true, locate() { throw new Error('Remote must not request GPS'); },
        async fetch(_, options) {
            assert.equal('latitude' in JSON.parse(options.body), false);
            return { ok: true, status: 200, json: async () => ({ id: 1 }) };
        },
    });
    await remote.submit();
    assert.deepEqual(remote.navigations, ['/app/attendance/1']);
});

test('expired sessions with an HTML response request reauthentication instead of reporting uncertainty', async () => {
    const ui = screen({ remote: true, async fetch() { return { status: 419, ok: false, json: async () => { throw new Error('HTML CSRF error page'); } }; } });
    await ui.submit();
    assert.match(ui.feedback.textContent, /Sesi atau izin sudah berubah/);
    assert.equal(ui.navigations.length, 0);
    assert.equal(ui.button.disabled, false);
});
