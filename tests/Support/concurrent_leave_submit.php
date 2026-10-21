<?php

use App\Domain\Workflow\WorkflowService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (! $app->environment('testing') || ! preg_match('/^hris_concurrency_test_[a-f0-9]{16}$/D', $database) || getenv('HRIS_CONCURRENCY_DATABASE') !== $database) {
    throw new RuntimeException('Concurrency fixture is restricted to its disposable test database.');
}
echo "READY\n";
flush();
$deadline = microtime(true) + 20;
while (file_exists($argv[3])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Test barrier timeout.');
    }
    usleep(10000);
    clearstatcache(true, $argv[3]);
}
try {
    app(WorkflowService::class)->submit(User::findOrFail((int) $argv[1]), (int) $argv[2], 1, $argv[4]);
    $status = 200;
} catch (ValidationException $e) {
    $status = 422;
} catch (HttpExceptionInterface $e) {
    $status = $e->getStatusCode();
}
echo json_encode(['status' => $status, 'request_id' => (int) $argv[2]])."\n";
