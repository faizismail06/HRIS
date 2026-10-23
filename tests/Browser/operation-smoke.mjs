import {spawn} from 'node:child_process';
import {readFile, writeFile} from 'node:fs/promises';
import {pathToFileURL} from 'node:url';
import path from 'node:path';

const [binary, fixture, profile, screenshot, viewportWidth] = process.argv.slice(2);
const width = Number(viewportWidth);
if (![360, 390, 1100, 1440].includes(width) || !path.resolve(fixture).startsWith(path.resolve('.cache/operation-browser') + path.sep)) throw new Error('Only isolated project fixtures are supported');
const browser = spawn(binary, ['--headless=new', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
let errors = '';
browser.stderr.on('data', (chunk) => { errors += chunk; });
let ws;
let id = 0;
const pending = new Map();
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const call = (method, params = {}) => new Promise((resolve, reject) => {
    const request = ++id;
    pending.set(request, {resolve, reject});
    ws.send(JSON.stringify({id: request, method, params}));
});
try {
    let port;
    for (let i = 0; i < 100; i++) {
        try { port = (await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]; break; }
        catch { await pause(100); }
    }
    if (!port) throw new Error(`Isolated browser did not start: ${errors.slice(-1000)}`);
    const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    const page = pages.find((item) => item.type === 'page');
    if (!page) throw new Error('No isolated browser page');
    ws = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { ws.addEventListener('open', resolve, {once: true}); ws.addEventListener('error', reject, {once: true}); });
    ws.addEventListener('message', (event) => {
        const message = JSON.parse(event.data);
        if (!message.id || !pending.has(message.id)) return;
        const receiver = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) receiver.reject(new Error(message.error.message)); else receiver.resolve(message.result);
    });
    await call('Page.enable');
    await call('Emulation.setDeviceMetricsOverride', {width, height: 1100, deviceScaleFactor: 1, mobile: width <= 390});
    await call('Page.navigate', {url: pathToFileURL(path.resolve(fixture)).href});
    let result;
    for (let i = 0; i < 80; i++) {
        result = await call('Runtime.evaluate', {expression: "document.querySelector('#browser-smoke-result')?.textContent || ''", returnByValue: true});
        if (result.result.value) break;
        await pause(100);
    }
    const metrics = await call('Page.getLayoutMetrics');
    const image = await call('Page.captureScreenshot', {format: 'png', captureBeyondViewport: true, clip: {x: 0, y: 0, width, height: Math.min(5000, Math.ceil(metrics.cssContentSize.height)), scale: 1}});
    await writeFile(screenshot, Buffer.from(image.data, 'base64'));
    const output = await call('Runtime.evaluate', {expression: 'document.documentElement.outerHTML', returnByValue: true});
    process.stdout.write(output.result.value);
    if (result?.result.value !== 'PASS') process.exitCode = 1;
} catch (error) {
    process.stderr.write(error.message);
    process.exitCode = 1;
} finally {
    if (ws?.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({id: ++id, method: 'Browser.close'}));
        await pause(200);
        ws.close();
    }
    if (browser.exitCode === null) browser.kill();
}
