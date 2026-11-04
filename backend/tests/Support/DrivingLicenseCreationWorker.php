<?php

use App\Http\Controllers\Api\DrivingLicenseCatalogController;
use App\Http\Controllers\Api\DrivingLicenseTypeController;
use App\Models\Account;
use App\Services\AuditLogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? null;
$actorId = $argv[2] ?? null;
$typeId = $argv[3] ?? null;
$code = $argv[4] ?? null;

if (
    !in_array($mode, ['type', 'catalog'], true)
    || !$actorId
    || !$code
    || ($mode === 'catalog' && !$typeId)
) {
    fwrite(STDERR, "Invalid driving license worker arguments.\n");
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

    if ($mode === 'type') {
        $request = Request::create(
            '/api/admin/driving-license-types',
            'POST',
            [
                'code' => $code,
                'name' => 'Concurrency '.$code,
            ]
        );

        $request->attributes->set('account', $actor);

        $response = $app->make(DrivingLicenseTypeController::class)
            ->store(
                $request,
                $app->make(AuditLogService::class)
            );
    } else {
        $request = Request::create(
            '/api/admin/driving-license-catalog',
            'POST',
            [
                'driving_license_type_id' => $typeId,
                'code' => $code,
                'name' => 'Concurrency '.$code,
            ]
        );

        $request->attributes->set('account', $actor);

        $response = $app->make(DrivingLicenseCatalogController::class)
            ->store(
                $request,
                $app->make(AuditLogService::class)
            );
    }

    $status = $response->getStatusCode();

    echo json_encode([
        'result' => $status === 201 ? 'SUCCESS' : 'REJECTED',
        'status' => $status,
    ], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    echo json_encode([
        'result' => 'EXCEPTION',
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR)."\n";
}

fflush(STDOUT);
