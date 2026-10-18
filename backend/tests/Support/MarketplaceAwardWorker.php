<?php

use App\Models\Account;
use App\Services\MarketplaceService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$customerId = $argv[1] ?? null;
$requestId = $argv[2] ?? null;
$quotationId = $argv[3] ?? null;

if (!$customerId || !$requestId || !$quotationId) {
    fwrite(STDERR, "Missing award worker arguments.\n");
    exit(1);
}

echo "READY\n";
fflush(STDOUT);

$signal = fgets(STDIN);

if (trim((string) $signal) !== 'GO') {
    fwrite(STDERR, "Award worker did not receive GO signal.\n");
    exit(1);
}

try {
    $customer = Account::query()->findOrFail($customerId);

    $task = $app->make(MarketplaceService::class)->awardQuotation(
        $customer,
        $requestId,
        $quotationId
    );

    echo json_encode([
        'result' => 'SUCCESS',
        'task_id' => $task->task_id,
        'assigned_account_id' => $task->assigned_account_id,
    ], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    echo json_encode([
        'result' => 'REJECTED',
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR)."\n";
}

fflush(STDOUT);
