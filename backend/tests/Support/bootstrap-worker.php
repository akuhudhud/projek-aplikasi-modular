<?php

use Illuminate\Contracts\Console\Kernel;

$writeBootDiagnostic = static function (string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
    fflush(STDERR);
};

$writeBootDiagnostic('BOOT_START');

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $message = sprintf(
        "BOOT_FATAL: %s in %s:%d\n",
        $error['message'],
        $error['file'],
        $error['line']
    );

    fwrite(STDERR, $message);
    fflush(STDERR);
});

try {
    $backendPath = dirname(__DIR__, 2);

    $writeBootDiagnostic('BOOT_LOADING_AUTOLOAD');

    require $backendPath . '/vendor/autoload.php';

    $writeBootDiagnostic('BOOT_AUTOLOAD_READY');

    $app = require $backendPath . '/bootstrap/app.php';

    $writeBootDiagnostic('BOOT_APP_CREATED');

    $kernel = $app->make(Kernel::class);

    $writeBootDiagnostic('BOOT_KERNEL_CREATED');

    $kernel->bootstrap();

    $writeBootDiagnostic('BOOTSTRAP_COMPLETE');

    return $app;
} catch (Throwable $exception) {
    $message = sprintf(
        "BOOT_ERROR: %s: %s in %s:%d\n",
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    fwrite(STDERR, $message);
    fflush(STDERR);

    exit(1);
}
