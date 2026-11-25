
<?php

use Illuminate\Contracts\Console\Kernel;

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

    fwrite(STDOUT, $message);
    fflush(STDOUT);
});

try {
    require dirname(__DIR__, 2).'/vendor/autoload.php';

    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    return $app;
} catch (Throwable $exception) {
    $message = sprintf(
        "BOOT_ERROR: %s: %s\n",
        get_class($exception),
        $exception->getMessage()
    );

    fwrite(STDOUT, $message);
    fwrite(STDERR, $message);
    fflush(STDOUT);
    fflush(STDERR);

    exit(1);
}
