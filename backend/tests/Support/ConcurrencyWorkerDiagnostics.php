<?php

namespace Tests\Support;

use RuntimeException;

final class ConcurrencyWorkerDiagnostics
{
    public static function assertReady(
        array &$worker,
        string $failureMessage
    ): void {
        $ready = fgets($worker['pipes'][1]);

        if ($ready === "READY\n") {
            return;
        }

        $stdout = is_resource($worker['pipes'][1])
            ? stream_get_contents($worker['pipes'][1])
            : '';

        $stderr = is_resource($worker['pipes'][2])
            ? stream_get_contents($worker['pipes'][2])
            : '';

        $status = is_resource($worker['process'])
            ? proc_get_status($worker['process'])
            : [];

        foreach ($worker['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = $status['exitcode'] ?? null;

        if (is_resource($worker['process'])) {
            proc_close($worker['process']);
        }

        $worker['process'] = null;

        throw new RuntimeException(
            $failureMessage."\n"
            ."READY received: ".var_export($ready, true)."\n"
            ."Process running: ".var_export($status['running'] ?? null, true)."\n"
            ."Process exit code: ".var_export($exitCode, true)."\n"
            ."STDOUT: ".trim((string) $stdout)."\n"
            ."STDERR: ".trim((string) $stderr)
        );
    }
}
