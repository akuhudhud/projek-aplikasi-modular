<?php

namespace Tests\Support;

use RuntimeException;

final class ConcurrencyWorkerDiagnostics
{
    public static function assertReady(
        array &$worker,
        string $failureMessage
    ): void {
        $stdoutPipe = $worker['pipes'][1];
        $stderrPipe = $worker['pipes'][2];

        stream_set_blocking($stdoutPipe, false);
        stream_set_blocking($stderrPipe, false);

        $stdout = '';
        $stderr = '';
        $ready = false;
        $deadline = microtime(true) + 30;

        while (microtime(true) < $deadline) {
            $stdoutChunk = fread($stdoutPipe, 8192);
            $stderrChunk = fread($stderrPipe, 8192);

            if ($stdoutChunk !== false && $stdoutChunk !== '') {
                $stdout .= $stdoutChunk;
            }

            if ($stderrChunk !== false && $stderrChunk !== '') {
                $stderr .= $stderrChunk;
            }

            $firstLine = strtok($stdout, "\n");

            if (
                $firstLine !== false
                && rtrim($firstLine, "\r") === 'READY'
            ) {
                $ready = true;
                break;
            }

            $status = proc_get_status($worker['process']);

            if (!$status['running']) {
                break;
            }

            usleep(10000);
        }

        if ($ready) {
            stream_set_blocking($stdoutPipe, true);
            stream_set_blocking($stderrPipe, true);

            return;
        }

        $stdout .= stream_get_contents($stdoutPipe);
        $stderr .= stream_get_contents($stderrPipe);

        $status = proc_get_status($worker['process']);

        foreach ($worker['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (
            is_resource($worker['process'])
            && ($status['running'] ?? false)
        ) {
            proc_terminate($worker['process']);
        }

        $exitCode = proc_close($worker['process']);
        $worker['process'] = null;

        throw new RuntimeException(
            $failureMessage . "\n"
            . "READY received: " . var_export($ready, true) . "\n"
            . "Process running: "
            . var_export($status['running'] ?? null, true) . "\n"
            . "Process exit code: " . var_export($exitCode, true) . "\n"
            . "STDOUT: " . trim($stdout) . "\n"
            . "STDERR: " . trim($stderr)
        );
    }
}
