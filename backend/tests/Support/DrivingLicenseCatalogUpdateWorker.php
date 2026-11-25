<?php

use App\Http\Controllers\Api\DrivingLicenseCatalogController;
use App\Models\Account;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

$app = require __DIR__.'/bootstrap-worker.php';

$actorId = $argv[1] ?? null;
$catalogId = $argv[2] ?? null;
$newCode = $argv[3] ?? null;

if (!$actorId || !$catalogId || !$newCode) {
    fwrite(STDERR, "Invalid driving license catalog update worker arguments.\n");
    exit(1);
}

echo "READY\n";
fflush(STDOUT);

$signal = fgets(STDIN);

if (trim((string) $signal) !== 'GO') {
    fwrite(STDERR, "Worker did not receive GO signal.\n");
    exit(1);
}

try {
    $actor = Account::query()->findOrFail($actorId);

    $request = Request::create(
        '/api/admin/driving-license-catalog/'.$catalogId,
        'PATCH',
        ['code' => $newCode]
    );

    $request->attributes->set('account', $actor);

    $response = $app->make(DrivingLicenseCatalogController::class)
        ->update(
            $request,
            $catalogId,
            $app->make(AuditLogService::class)
        );

    $status = $response->getStatusCode();

    echo json_encode([
        'result' => $status === 200 ? 'SUCCESS' : 'REJECTED',
        'status' => $status,
        'body' => $response->getData(true),
    ], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    echo json_encode([
        'result' => 'EXCEPTION',
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR)."\n";
}

fflush(STDOUT);
