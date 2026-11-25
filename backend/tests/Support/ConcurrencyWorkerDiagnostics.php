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

        $buffer = '';
        $deadline = microtime(true) + 30;
        $ready = false;

        while (microtime(true) < $deadline) {
            $read = [$stdoutPipe];
            $write = null;
            $except = null;

            $selected = @stream_select(
                $read,
                $write,
                $except,
                0,
                200000
            );

            if ($selected === false) {
                break;
            }

            if ($selected > 0) {
                $chunk = fread($stdoutPipe, 8192);

                if ($chunk !== false && $chunk !== '') {
                    $buffer .= $chunk;

                    $newline = strpos($buffer, "\n");

                    if ($newline !== false) {
                        $firstLine = substr($buffer, 0, $newline);

                        if (rtrim($firstLine, "\r") === 'READY') {
                            $ready = true;
                        }

                        break;
                    }
                }
            }

            $status = proc_get_status($worker['process']);

            if (!$status['running']) {
                break;
            }
        }

        if ($ready) {
            stream_set_blocking($stdoutPipe, true);
            stream_set_blocking($stderrPipe, true);

            return;
        }

        $stdout = $buffer . stream_get_contents($stdoutPipe);
        $stderr = stream_get_contents($stderrPipe);

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
