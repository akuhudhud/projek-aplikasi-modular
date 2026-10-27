<?php

use App\Http\Controllers\Api\RunnerLicenseVerificationController;
use App\Models\Account;
use App\Services\AuditLogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$actorId = $argv[1] ?? null;
$verificationId = $argv[2] ?? null;
$decision = $argv[3] ?? null;

if (
    !$actorId
    || !$verificationId
    || !in_array($decision, ['VERIFIED', 'REJECTED'], true)
) {
    fwrite(STDERR, "Invalid license decision worker arguments.\n");
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
        '/internal-test/license-decision',
        'POST',
        [
            'decision' => $decision,
            'rejection_reason' => $decision === 'REJECTED'
                ? 'Concurrency test rejection.'
                : null,
        ]
    );

    $request->attributes->set('account', $actor);

    $controller = $app->make(
        RunnerLicenseVerificationController::class
    );

    $response = $controller->decide(
        $request,
        $verificationId,
        $app->make(AuditLogService::class)
    );

    echo json_encode([
        'http_status' => $response->getStatusCode(),
        'decision' => $decision,
        'response' => json_decode(
            $response->getContent(),
            true
        ),
    ], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        get_class($exception).': '.$exception->getMessage()."\n"
    );
    exit(1);
}

fflush(STDOUT);
