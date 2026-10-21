<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\AttendanceScenarioCase;

/** Uses isolated synthetic responses, not the operator's browser profile or live database. */
class OperationBrowserTest extends AttendanceScenarioCase
{
    public function test_browser_populates_reference_versions_and_preserves_selected_records(): void
    {
        $this->login($this->user('it_asset_officer'));
        $html = $this->get('/app/work/asset-issue')->assertOk()->getContent();
        $this->browser($html, <<<'JS'
const asset = form.elements.record_id;
asset.value = '10'; asset.dispatchEvent(new Event('change'));
if (form.elements.expected_version.value !== '3') throw new Error('Asset version was not populated');
form.elements.employee_id.value = '20'; form.elements.employee_id.dispatchEvent(new Event('change'));
asset.value = '11'; asset.dispatchEvent(new Event('change'));
if (form.elements.expected_version.value !== '4') throw new Error('Changed record retained an old version');
if (new FormData(form).get('record_id') !== '11') throw new Error('Selected reference was lost');
if (!document.querySelector('label[for="record_id"]')) throw new Error('Label association is missing');
JS, 'asset');
    }

    public function test_browser_repeater_mapping_uses_actual_headers_and_removes_rows(): void
    {
        $this->login($this->hr);
        $html = $this->get('/app/work/import-map?record_id=10')->assertOk()->getContent();
        $this->browser($html, <<<'JS'
if (form.elements.expected_version.value !== '3') throw new Error('Import version was not populated');
const group = [...form.querySelectorAll('fieldset')].find((el) => el.querySelector('legend')?.textContent.startsWith('Kolom tujuan'));
group.querySelector(':scope > button').click();
const target = form.querySelector('[name="mapping[0][key]"]');
const source = form.querySelector('[name="mapping[0][value]"]');
if (target.tagName !== 'SELECT' || source.tagName !== 'SELECT') throw new Error('Mapping is not a structured selection');
target.value = 'code'; source.value = 'Code';
if (new FormData(form).get('mapping[0][value]') !== 'Code') throw new Error('Mapped column was lost');
group.querySelector('.operation-row button').click();
if (form.querySelector('.operation-row')) throw new Error('Row removal failed');
JS, 'import');
    }

    public function test_browser_preserves_zero_profile_version_and_hides_fixed_category(): void
    {
        $this->login($this->worker);
        $html = $this->get('/app/work/request-bank_change?employee_id=20')->assertOk()->getContent();
        $this->browser($html, <<<'JS'
if (form.elements.base_version.value !== '0') throw new Error('Zero profile version was lost');
if (form.elements.category.value !== 'bank' || !form.elements.category.closest('[hidden]')) throw new Error('Fixed profile category is not hidden');
if (document.documentElement.scrollWidth > window.innerWidth) throw new Error('Form overflows horizontally');
JS, 'profile');
    }

    private function browser(string $html, string $assertions, string $name): void
    {
        $binary = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
        if (! is_file($binary)) {
            $this->markTestSkipped('Chrome headless is unavailable; use the supported browser QA environment.');
        }
        $directory = base_path('.cache/operation-browser');
        File::ensureDirectoryExists($directory);
        $css = '';
        if (is_file(public_path('build/manifest.json'))) {
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
        }
        $html = preg_replace('/<script\b[^>]*type="module"[^>]*>.*?<\/script>|<link\b[^>]*>/s', '', $html);
        $html = str_replace('</head>', '<style>'.$css.'</style></head>', $html);
        $mock = <<<'JS'
window.fetch = async (url) => {
    const path = new URL(url, location.href).pathname;
    const source = path.split('/').pop();
    if (source === 'profile-version') return new Response(JSON.stringify({version: 0}), {status: 200});
    const data = source === 'people' ? [{value: 20, label: 'Synthetic Worker', meta: {version: 1}}] : [{value: 10, label: 'Synthetic reference', meta: {version: 3, ...(source === 'imports' ? {checksum: 'a'.repeat(64), headers: ['Code','Name'], fields: ['code','name']} : {})}}, {value: 11, label: 'Synthetic reference 2', meta: {version: 4}}];
    return new Response(JSON.stringify({data, next: null}), {status: 200, headers: {'Content-Type': 'application/json'}});
};
window.addEventListener('error', (event) => { document.body.dataset.scriptError = event.message; });
JS;
        $verify = "setTimeout(() => { const out = document.createElement('output'); out.id = 'browser-smoke-result'; document.body.append(out); try { const form = document.querySelector('[data-operation-form]'); if (document.body.dataset.scriptError) throw new Error(document.body.dataset.scriptError); ".$assertions."; out.textContent = 'PASS'; } catch(error) { out.textContent = 'FAIL: ' + error.message; } }, 500);";
        $html = str_replace('</body>', '<script>'.$mock.'</script><script>'.file_get_contents(resource_path('js/operations.js')).'</script><script>'.$verify.'</script></body>', $html);
        $path = $directory.'/'.$name.'.html';
        file_put_contents($path, $html);
        $process = new Process(['node', base_path('tests/Browser/operation-smoke.mjs'), $binary, $path, $directory.'/profile-'.$name.'-'.bin2hex(random_bytes(4)), $directory.'/'.$name.'.png', $name === 'asset' ? '1100' : '390'], base_path());
        $process->setTimeout(30);
        $process->run();
        $dom = $process->getOutput();
        file_put_contents($directory.'/'.$name.'-result.json', json_encode(['exit' => $process->getExitCode(), 'error' => $process->getErrorOutput(), 'output_bytes' => strlen($dom)]));
        $this->assertStringContainsString('<output id="browser-smoke-result">PASS</output>', $dom, 'Browser exit '.$process->getExitCode().': '.substr($process->getErrorOutput(), 0, 800).substr(strip_tags($dom), -1000));
    }
}
