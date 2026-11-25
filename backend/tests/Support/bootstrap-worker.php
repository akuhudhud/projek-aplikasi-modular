<?php

use Illuminate\Contracts\Console\Kernel;

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
