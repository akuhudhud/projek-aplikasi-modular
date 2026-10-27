<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\RunnerLicenseVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseDecisionConcurrencyTest extends TestCase
{
    private array $workers = [];

    public function test_only_one_concurrent_license_decision_is_recorded(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'License decision concurrency requires MySQL row locking.'
            );
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped(
                'License decision concurrency requires proc_open.'
            );
        }

        $suffix = strtolower(str_replace('-', '', (string) Str::uuid()));
        $actorId = (string) Str::uuid();
        $targetId = (string) Str::uuid();
        $verificationId = (string) Str::uuid();
        $now = now();

        try {
            DB::table('accounts')->insert([
                [
                    'id' => $actorId,
                    'name' => 'Concurrency Reviewer '.$suffix,
                    'password' => bcrypt('TestPassword123'),
                    'role' => 'ROOT_SUPER_ADMIN',
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => $targetId,
                    'name' => 'Concurrency Applicant '.$suffix,
                    'password' => bcrypt('TestPassword123'),
                    'role' => 'USER',
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            RunnerLicenseVerification::query()->create([
                'id' => $verificationId,
                'account_id' => $targetId,
                'license_number_encrypted' => Crypt::encryptString(
                    'D123456789012'
                ),
                'license_type' => 'GENERAL',
                'license_class' => 'D',
                'previous_license_verification_id' => null,
                'expires_at' => now()->addYear()->toDateString(),
                'status' => RunnerLicenseVerification::STATUS_PENDING,
                'is_current' => false,
                'submitted_at' => $now,
                'reviewed_at' => null,
                'reviewer_account_id' => null,
                'rejection_reason' => null,
                'disk' => 'local',
                'document_path' => 'concurrency/'.$verificationId.'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 100,
            ]);

            $workerPath = __DIR__.'/../../Support/RunnerLicenseDecisionWorker.php';

            $this->startWorker(
                $workerPath,
                $actorId,
                $verificationId,
                'VERIFIED'
            );

            $this->startWorker(
                $workerPath,
                $actorId,
                $verificationId,
                'REJECTED'
            );

            foreach ($this->workers as $worker) {
                $this->assertSame(
                    "READY\n",
                    fgets($worker['pipes'][1]),
                    'A decision worker did not reach the concurrency barrier.'
                );
            }

            foreach ($this->workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
                fclose($worker['pipes'][0]);
            }

            $results = [];

            foreach ($this->workers as $index => $worker) {
                $output = stream_get_contents($worker['pipes'][1]);
                $errorOutput = stream_get_contents($worker['pipes'][2]);

                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);

                $exitCode = proc_close($worker['process']);
                $this->workers[$index]['process'] = null;

                $this->assertSame(
                    0,
                    $exitCode,
                    'Decision worker failed: '.$errorOutput
                );

                $results[] = json_decode(
                    trim($output),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }

            $successes = array_values(array_filter(
                $results,
                fn (array $result): bool =>
                    ($result['http_status'] ?? null) === 200
            ));

            $rejections = array_values(array_filter(
                $results,
                fn (array $result): bool =>
                    ($result['http_status'] ?? null) === 422
            ));

            $this->assertCount(1, $successes, json_encode($results));
            $this->assertCount(1, $rejections, json_encode($results));

            $this->assertSame(
                RunnerLicenseVerification::STATUS_PENDING,
                $results[0]['initial_status'] ?? null,
                'Worker output must report the shared initial status.'
            );
        } finally {
            $this->stopWorkers();

            AuditLog::query()
                ->where('target_account_id', $targetId)
                ->whereIn('actor_account_id', [$actorId])
                ->delete();

            RunnerLicenseVerification::query()
                ->whereKey($verificationId)
                ->delete();

            DB::table('accounts')
                ->whereIn('id', [$actorId, $targetId])
                ->delete();
        }

        $verification = RunnerLicenseVerification::query()
            ->find($verificationId);

        $this->assertNull(
            $verification,
            'Test cleanup should remove its temporary verification record.'
        );
    }

    private function startWorker(
        string $workerPath,
        string $actorId,
        string $verificationId,
        string $decision
    ): void {
        $pipes = [];

        $process = proc_open(
            [
                PHP_BINARY,
                $workerPath,
                $actorId,
                $verificationId,
                $decision,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3)
        );

        $this->assertIsResource(
            $process,
            'Unable to start license decision worker.'
        );

        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);

        $this->workers[] = [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    private function stopWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if (!is_resource($worker['process'])) {
                continue;
            }

            $status = proc_get_status($worker['process']);

            if ($status['running']) {
                proc_terminate($worker['process']);
            }

            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_close($worker['process']);
        }

        $this->workers = [];
    }
}
